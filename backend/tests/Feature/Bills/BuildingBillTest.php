<?php

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

    // 50, 30 in 20 m² (primer iz US-07).
    $this->a = Apartment::factory()->for($this->building)->create(['label' => 'A', 'area_m2' => 50]);
    $this->b = Apartment::factory()->for($this->building)->create(['label' => 'B', 'area_m2' => 30]);
    $this->c = Apartment::factory()->for($this->building)->create(['label' => 'C', 'area_m2' => 20]);

    // A: dva sostanovalca celo obdobje; B: en najemnik; C: prazno.
    $this->leaseA = Lease::factory()->for($this->a)->withTenants([30000, 30000])->create(['status' => LeaseStatus::Active, 'starts_on' => '2026-01-01']);
    $this->leaseB = Lease::factory()->for($this->b)->withTenants([40000])->create(['status' => LeaseStatus::Active, 'starts_on' => '2026-01-01']);
});

function buildingBill(string $method, int $amount = 30000, string $action = ''): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->landlord)->postJson('/api/buildings/'.test()->building->id.'/utility-bills'.$action, [
        'scope' => 'building',
        'type' => 'heating',
        'amount_cents' => $amount,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'split_method' => $method,
    ]);
}

it('po površini razdeli 300,00 € na 150,00 €, 90,00 € in 60,00 €', function () {
    $apartments = buildingBill('area', 30000, '/preview')->assertOk()->json('data.apartments');

    expect(collect($apartments)->map(fn ($a) => [$a['label'], $a['amount_cents']])->all())
        ->toBe([['A', 15000], ['B', 9000], ['C', 6000]]);
});

it('po številu oseb deli sorazmerno z dnevi bivanja vseh najemnikov stanovanja', function () {
    // A: 2 × 30 dni = 60, B: 30 dni, C: 0 → 2/3 in 1/3.
    $apartments = buildingBill('persons', 30000, '/preview')->json('data.apartments');

    expect(collect($apartments)->map(fn ($a) => [$a['label'], $a['days'], $a['amount_cents']])->all())
        ->toBe([['A', 60, 20000], ['B', 30, 10000]]);
});

it('na enake dele razdeli znesek med vsa stanovanja', function () {
    $apartments = buildingBill('equal', 10000, '/preview')->json('data.apartments');

    expect(collect($apartments)->pluck('amount_cents')->all())->toBe([3334, 3333, 3333]);
});

it('delež praznega stanovanja je delež lastnika in ne postane obveznost', function () {
    buildingBill('area')->assertCreated();

    $landlordShare = BillShare::whereNull('lease_tenant_id')->sole();
    expect($landlordShare->apartment_id)->toBe($this->c->id)
        ->and($landlordShare->amount_cents)->toBe(6000)
        ->and(Charge::whereHas('leaseTenant.lease', fn ($q) => $q->where('apartment_id', $this->c->id))->count())->toBe(0);
});

it('delež stanovanja razdeli med sostanovalce po dnevih, vsota je enaka položnici', function () {
    $this->leaseA->tenants[1]->update(['moved_in_on' => '2026-09-16']); // 15 dni

    buildingBill('area', 30001)->assertCreated();

    $bill = UtilityBill::sole();
    $sharesA = BillShare::where('apartment_id', $this->a->id)->orderBy('id')->get();

    // A dobi 150,01 € (ostanek centa gre največjemu stanovanju), razdeljeno 30 : 15 dni.
    expect($sharesA->pluck('days')->all())->toBe([30, 15])
        ->and($sharesA->sum('amount_cents'))->toBe(15001)
        ->and($sharesA->pluck('amount_cents')->all())->toBe([10001, 5000])
        ->and($bill->shares->sum('amount_cents'))->toBe(30001)
        ->and(Charge::sum('amount_cents'))->toBe(30001 - 6000);
});

it('zahteva način delitve in veljavne površine', function () {
    $this->actingAs($this->landlord)->postJson("/api/buildings/{$this->building->id}/utility-bills/preview", [
        'scope' => 'building', 'type' => 'heating', 'amount_cents' => 1000,
        'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
    ])->assertUnprocessable()->assertJsonValidationErrors('split_method');

    $this->c->update(['area_m2' => 0]);
    buildingBill('area', 1000, '/preview')->assertUnprocessable()->assertJsonValidationErrors('split_method');
});

it('najemnik vidi skupni znesek, način delitve, delež stanovanja in svoje dni, ne pa drugih stanovanj', function () {
    buildingBill('area')->assertCreated();
    $tenant = $this->leaseA->tenants[0];
    $charge = Charge::where('lease_tenant_id', $tenant->id)->sole();

    $source = $this->actingAs($tenant->user)->getJson("/api/charges/{$charge->id}/source")->assertOk()->json('data.data');

    expect($source['bill'])->toMatchArray(['amount_cents' => 30000, 'scope' => 'building', 'split_method_label' => 'po površini'])
        ->and($source['apartment'])->toBe(['label' => 'A', 'share_cents' => 15000, 'days' => 60])
        ->and($source['tenant'])->toBe(['days' => 30, 'amount_cents' => 7500])
        ->and(json_encode($source))->not->toContain('"B"')
        ->and(json_encode($source))->not->toContain('9000');
});
