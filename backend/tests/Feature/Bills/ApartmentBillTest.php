<?php

use App\Enums\ChargeType;
use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\BillShare;
use App\Models\Building;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\UtilityBill;

beforeEach(function () {
    $this->travelTo('2026-10-05 10:00');
    $this->building = Building::factory()->create(['rent_due_day' => 8]);
    $this->landlord = memberOf($this->building, Role::Landlord);
    $this->apartment = Apartment::factory()->for($this->building)->create(['label' => '2C']);

    // Jan in Nik celo obdobje, Sara od 16. 9. (kot v wireframu WF-04).
    $this->lease = Lease::factory()->for($this->apartment)->withTenants([30000, 30000, 30000])
        ->create(['status' => LeaseStatus::Active, 'starts_on' => '2026-07-01']);
    $this->lease->tenants[2]->update(['moved_in_on' => '2026-09-16']);
    [$this->jan, $this->nik, $this->sara] = $this->lease->tenants->all();
});

function billPayload(array $overrides = []): array
{
    return [
        'scope' => 'apartment',
        'apartment_id' => test()->apartment->id,
        'type' => 'electricity',
        'amount_cents' => 9000,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        ...$overrides,
    ];
}

it('predogled razdeli položnico po dnevih bivanja: 36,00 €, 36,00 € in 18,00 €', function () {
    $preview = $this->actingAs($this->landlord)
        ->postJson("/api/buildings/{$this->building->id}/utility-bills/preview", billPayload())
        ->assertOk()
        ->json('data');

    expect($preview['days_in_period'])->toBe(30)
        ->and($preview['total_cents'])->toBe(9000)
        ->and($preview['matches_bill'])->toBeTrue()
        ->and($preview['charge_period_label'])->toBe('november')
        ->and(collect($preview['apartments'][0]['shares'])->map(fn ($s) => [$s['days'], $s['amount_cents']])->all())
        ->toBe([[30, 3600], [30, 3600], [15, 1800]]);

    // Predogled ničesar ne shrani.
    expect(UtilityBill::count())->toBe(0);
});

it('šteje dan vselitve in dan izselitve ter samo dneve v obdobju', function () {
    $this->nik->update(['moved_out_on' => '2026-09-10']); // 1.–10. 9. = 10 dni

    $shares = $this->actingAs($this->landlord)
        ->postJson("/api/buildings/{$this->building->id}/utility-bills/preview", billPayload(['amount_cents' => 11000]))
        ->json('data.apartments.0.shares');

    expect(collect($shares)->pluck('days')->all())->toBe([30, 10, 15])
        ->and(array_sum(array_column($shares, 'amount_cents')))->toBe(11000);
});

it('shrani položnico, deleže in obveznosti stroškov za naslednji mesec', function () {
    $this->actingAs($this->landlord)
        ->postJson("/api/buildings/{$this->building->id}/utility-bills", billPayload())
        ->assertCreated()
        ->assertJsonPath('data.scope', 'apartment')
        ->assertJsonPath('data.apartment.label', '2C');

    $bill = UtilityBill::sole();
    expect($bill->building_id)->toBe($this->building->id)
        ->and($bill->apartment_id)->toBe($this->apartment->id)
        ->and($bill->shares->sum('amount_cents'))->toBe(9000);

    $charges = Charge::orderBy('id')->get();
    expect($charges)->toHaveCount(3)
        ->and($charges->pluck('amount_cents')->all())->toBe([3600, 3600, 1800])
        ->and($charges[0]->type)->toBe(ChargeType::Utility)
        ->and($charges[0]->period->toDateString())->toBe('2026-11-01')
        ->and($charges[0]->due_date->toDateString())->toBe('2026-11-08')
        ->and($charges[0]->bill_share_id)->not->toBeNull()
        ->and($charges[0]->description)->toBe('Elektrika 1. 9. 2026 – 30. 9. 2026 (2C)');
});

it('100,00 € med tri sostanovalce z enakimi dnevi: 33,34 €, 33,33 €, 33,33 €', function () {
    $this->sara->update(['moved_in_on' => '2026-07-01']);

    $this->actingAs($this->landlord)
        ->postJson("/api/buildings/{$this->building->id}/utility-bills", billPayload(['amount_cents' => 10000]))
        ->assertCreated();

    expect(BillShare::orderBy('id')->pluck('amount_cents')->all())->toBe([3334, 3333, 3333]);
});

it('če v obdobju ni živel nihče, znesek ostane lastniku in obveznost ne nastane', function () {
    $empty = Apartment::factory()->for($this->building)->create();

    $this->actingAs($this->landlord)
        ->postJson("/api/buildings/{$this->building->id}/utility-bills", billPayload(['apartment_id' => $empty->id]))
        ->assertCreated();

    $share = BillShare::sole();
    expect($share->isLandlordShare())->toBeTrue()
        ->and($share->amount_cents)->toBe(9000)
        ->and(Charge::count())->toBe(0);
});

it('zahteva vrsto, znesek in obdobje; konec ne sme biti pred začetkom', function () {
    $url = "/api/buildings/{$this->building->id}/utility-bills/preview";

    $this->actingAs($this->landlord)->postJson($url, ['scope' => 'apartment'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['apartment_id', 'type', 'amount_cents', 'period_start', 'period_end']);

    $this->actingAs($this->landlord)->postJson($url, billPayload(['period_end' => '2026-08-31']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['period_end' => 'Konec obdobja ne sme biti pred začetkom.']);

    $this->actingAs($this->landlord)->postJson($url, billPayload(['type' => 'plin']))
        ->assertUnprocessable()->assertJsonValidationErrors('type');
});

it('stanovanje mora pripadati stavbi', function () {
    $foreign = Apartment::factory()->create();

    $this->actingAs($this->landlord)
        ->postJson("/api/buildings/{$this->building->id}/utility-bills/preview", billPayload(['apartment_id' => $foreign->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('apartment_id');
});

it('če shranjevanje ne uspe, se ne shrani niti položnica niti noben delež', function () {
    Charge::creating(function (Charge $charge) {
        if ($charge->amount_cents === 1800) {
            throw new RuntimeException('Simulirana napaka');
        }
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($this->landlord)
        ->postJson("/api/buildings/{$this->building->id}/utility-bills", billPayload()))
        ->toThrow(RuntimeException::class);

    expect(UtilityBill::count())->toBe(0)
        ->and(BillShare::count())->toBe(0)
        ->and(Charge::count())->toBe(0);
});

it('položnice ne more vnesti najemnik', function () {
    $this->actingAs($this->jan->user)
        ->postJson("/api/buildings/{$this->building->id}/utility-bills", billPayload())
        ->assertForbidden();
});

it('najemnik ob obveznosti vidi izračun svojega deleža', function () {
    $this->actingAs($this->landlord)->postJson("/api/buildings/{$this->building->id}/utility-bills", billPayload());
    $saraCharge = Charge::where('lease_tenant_id', $this->sara->id)->sole();

    $this->actingAs($this->sara->user)->getJson("/api/charges/{$saraCharge->id}/source")
        ->assertOk()
        ->assertJsonPath('data.type', 'utility')
        ->assertJsonPath('data.data.bill.amount_cents', 9000)
        ->assertJsonPath('data.data.bill.type_label', 'Elektrika')
        ->assertJsonPath('data.data.apartment.share_cents', 9000)
        ->assertJsonPath('data.data.tenant.days', 15)
        ->assertJsonPath('data.data.tenant.amount_cents', 1800);
});
