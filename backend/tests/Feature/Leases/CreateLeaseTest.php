<?php

use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Lease;

beforeEach(function () {
    $this->building = Building::factory()->create();
    $this->apartment = Apartment::factory()->for($this->building)->create(['label' => '2C']);
    $this->landlord = memberOf($this->building, Role::Landlord);
});

function leasePayload(array $overrides = []): array
{
    return array_merge([
        'starts_on' => '2027-05-16',
        'ends_on' => null,
        'notice_period_days' => 30,
        'monthly_rent_cents' => 90000,
        'deposit_cents' => 180000,
        'tenants' => [
            ['name' => 'Jan Potočnik', 'email' => 'jan@example.com', 'rent_share_cents' => 45000],
            ['name' => 'Nik Golob', 'email' => 'nik@example.com', 'rent_share_cents' => 45000],
        ],
    ], $overrides);
}

it('shrani najem v stanju osnutek z najemniki in deleži', function () {
    $response = $this->actingAs($this->landlord)
        ->postJson("/api/apartments/{$this->apartment->id}/leases", leasePayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.ends_on', null)
        ->assertJsonCount(2, 'data.tenants');

    $lease = Lease::find($response->json('data.id'));
    expect($lease->tenants)->toHaveCount(2)
        ->and($lease->tenants->sum('rent_share_cents'))->toBe(90000)
        ->and($lease->tenants->first()->moved_in_on->toDateString())->toBe('2027-05-16');
});

it('zahteva obvezna polja in vsaj enega najemnika', function () {
    $this->actingAs($this->landlord)
        ->postJson("/api/apartments/{$this->apartment->id}/leases", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_on', 'notice_period_days', 'monthly_rent_cents', 'deposit_cents', 'tenants']);

    $this->actingAs($this->landlord)
        ->postJson("/api/apartments/{$this->apartment->id}/leases", leasePayload([
            'tenants' => [['name' => '', 'email' => 'ni-email', 'rent_share_cents' => 90000]],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenants.0.name', 'tenants.0.email']);
});

it('ne shrani najema, če vsota deležev ni enaka najemnini, in izpiše razliko', function () {
    $this->actingAs($this->landlord)
        ->postJson("/api/apartments/{$this->apartment->id}/leases", leasePayload([
            'tenants' => [
                ['name' => 'Jan Potočnik', 'email' => 'jan@example.com', 'rent_share_cents' => 45000],
                ['name' => 'Nik Golob', 'email' => 'nik@example.com', 'rent_share_cents' => 40000],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenants' => 'Deleži so za 50,00 € nižji od najemnine.']);

    expect(Lease::count())->toBe(0);
});

it('dovoli najem za nedoločen čas in zavrne konec pred začetkom', function () {
    $this->actingAs($this->landlord)
        ->postJson("/api/apartments/{$this->apartment->id}/leases", leasePayload(['ends_on' => '2027-01-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_on');
});

it('najemu ne more ustvariti najemnik ali lastnik druge stavbe', function () {
    $tenant = memberOf($this->building, Role::Tenant);
    $otherLandlord = memberOf(Building::factory()->create(), Role::Landlord);

    foreach ([$tenant, $otherLandlord] as $user) {
        $this->actingAs($user)
            ->postJson("/api/apartments/{$this->apartment->id}/leases", leasePayload())
            ->assertForbidden();
    }
});
