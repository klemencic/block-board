<?php

use App\Enums\ChargeType;
use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Charge;
use App\Models\Lease;
use App\Services\ChargeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->building = Building::factory()->create(['rent_due_day' => 8]);
    $this->generator = app(ChargeGenerator::class);
});

function leaseIn(Building $building, array $attributes = [], array $shares = [30000]): Lease
{
    return Lease::factory()
        ->for(Apartment::factory()->for($building))
        ->withTenants($shares)
        ->create(['starts_on' => '2027-01-01', 'status' => LeaseStatus::Active, ...$attributes]);
}

it('prvi dan v mesecu ustvari obveznost za najemnino v višini deleža z rokom iz nastavitev stavbe', function () {
    $lease = leaseIn($this->building, [], [30000, 20000]);

    $this->travelTo('2027-05-01 00:10');
    expect($this->generator->generate())->toBe(2);

    $charges = Charge::orderBy('id')->get();
    expect($charges->pluck('amount_cents')->all())->toBe([30000, 20000])
        ->and($charges[0]->type)->toBe(ChargeType::Rent)
        ->and($charges[0]->period->toDateString())->toBe('2027-05-01')
        ->and($charges[0]->due_date->toDateString())->toBe('2027-05-08')
        ->and($charges[0]->description)->toBe('Najemnina maj 2027')
        ->and($charges[0]->lease_tenant_id)->toBe($lease->tenants[0]->id);
});

it('vključi najeme v odpovednem roku, izloči osnutke, zaključene in prihodnje najeme', function () {
    leaseIn($this->building, ['status' => LeaseStatus::InNotice]);
    leaseIn($this->building, ['status' => LeaseStatus::Draft]);
    leaseIn($this->building, ['status' => LeaseStatus::Ended]);
    $future = leaseIn($this->building, ['starts_on' => '2027-07-01']);
    $future->tenants()->update(['moved_in_on' => '2027-07-01']);

    $this->travelTo('2027-05-01 00:10');

    expect($this->generator->generate())->toBe(1);
});

it('izseljeni sostanovalec ne dobi več obveznosti', function () {
    $lease = leaseIn($this->building, [], [30000, 20000]);
    $lease->tenants[1]->update(['moved_out_on' => '2027-04-15']);

    $this->travelTo('2027-05-01 00:10');

    expect($this->generator->generate())->toBe(1)
        ->and(Charge::sole()->lease_tenant_id)->toBe($lease->tenants[0]->id);
});

it('je idempotenten: ponovni zagon ne podvoji obveznosti', function () {
    leaseIn($this->building);
    $this->travelTo('2027-05-01 00:10');

    $this->generator->generate();
    $this->generator->generate();
    $this->artisan('charges:generate')->assertSuccessful();

    expect(Charge::count())->toBe(1);
});

it('baza prepreči dve najemnini za istega najemnika v istem mesecu', function () {
    $lease = leaseIn($this->building);
    $row = [
        'lease_tenant_id' => $lease->tenants[0]->id, 'type' => ChargeType::Rent, 'description' => 'x',
        'amount_cents' => 100, 'period' => '2027-05-01', 'due_date' => '2027-05-08',
    ];
    Charge::create($row);

    expect(fn () => Charge::create($row))->toThrow(UniqueConstraintViolationException::class);
});

it('za najem z začetkom med mesecem na dan začetka ustvari sorazmerni del', function () {
    $lease = leaseIn($this->building, ['starts_on' => '2027-05-16']);
    $lease->tenants()->update(['moved_in_on' => '2027-05-16']);

    $this->travelTo('2027-05-15 00:10');
    expect($this->generator->generate())->toBe(0);

    $this->travelTo('2027-05-16 00:10');
    expect($this->generator->generate())->toBe(1);

    // 16/31 × 300,00 € = 154,84 €
    $charge = Charge::sole();
    expect($charge->amount_cents)->toBe(15484)
        ->and($charge->period->toDateString())->toBe('2027-05-01')
        ->and($charge->due_date->toDateString())->toBe('2027-05-16')
        ->and($charge->description)->toBe('Najemnina maj 2027 (sorazmerno 16.–31. 5.)');

    // Naslednji mesec polna najemnina.
    $this->travelTo('2027-06-01 00:10');
    $this->generator->generate();
    expect(Charge::latest('id')->first()->amount_cents)->toBe(30000);
});

it('sorazmerni del upošteva dejansko število dni v mesecu', function (string $date, int $expected) {
    expect($this->generator->proratedAmount(30000, \Carbon\CarbonImmutable::parse($date)))->toBe($expected);
})->with([
    '16. maj (31 dni)' => ['2027-05-16', 15484],
    '15. februar 2027 (28 dni)' => ['2027-02-15', 15000],
    '15. februar 2028 (29 dni)' => ['2028-02-15', 15517],
    'prvi dan' => ['2027-05-01', 30000],
    'zadnji dan' => ['2027-04-30', 1000],
]);

it('aktivacija najema z začetkom med mesecem takoj ustvari sorazmerno obveznost', function () {
    $landlord = memberOf($this->building, Role::Landlord);
    $lease = leaseIn($this->building, ['starts_on' => '2027-05-16', 'status' => LeaseStatus::Draft]);
    $lease->tenants()->update(['moved_in_on' => '2027-05-16']);

    $this->travelTo('2027-05-20 10:00');
    $this->actingAs($landlord)->postJson("/api/leases/{$lease->id}/activate")->assertOk();

    expect(Charge::sole()->amount_cents)->toBe(15484);
});

it('osnutek najema ne ustvari obveznosti', function () {
    leaseIn($this->building, ['status' => LeaseStatus::Draft]);
    $this->travelTo('2027-05-01 00:10');

    expect($this->generator->generate())->toBe(0);
});

it('po zaključku najema za naslednji mesec ne nastane obveznost (US-02)', function () {
    $lease = leaseIn($this->building, ['status' => LeaseStatus::InNotice, 'notice_given_on' => '2027-04-01', 'terminates_on' => '2027-04-30']);

    $this->travelTo('2027-04-30 00:05');
    $this->artisan('leases:end-due');
    $this->travelTo('2027-05-01 00:10');
    $this->artisan('charges:generate');

    expect($lease->fresh()->status)->toBe(LeaseStatus::Ended)
        ->and(Charge::count())->toBe(0);
});
