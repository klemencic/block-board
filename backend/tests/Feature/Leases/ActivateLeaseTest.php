<?php

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Lease;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->building = Building::factory()->create();
    $this->apartment = Apartment::factory()->for($this->building)->create();
    $this->landlord = memberOf($this->building, Role::Landlord);
});

it('aktivira osnutek in stanovanje prikaže kot zasedeno', function () {
    $lease = Lease::factory()->for($this->apartment)->withTenants()->create();

    $this->actingAs($this->landlord)
        ->postJson("/api/leases/{$lease->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->actingAs($this->landlord)
        ->getJson("/api/buildings/{$this->building->id}/apartments")
        ->assertJsonPath('data.0.occupancy', 'occupied')
        ->assertJsonPath('data.0.current_lease_id', $lease->id);
});

it('zavrne aktivacijo, če ima stanovanje že aktiven najem, in vrne povezavo nanj', function (LeaseStatus $existingStatus) {
    $existing = Lease::factory()->for($this->apartment)->withTenants()->create(['status' => $existingStatus]);
    $draft = Lease::factory()->for($this->apartment)->withTenants()->create();

    $this->actingAs($this->landlord)
        ->postJson("/api/leases/{$draft->id}/activate")
        ->assertConflict()
        ->assertJson([
            'message' => 'Stanovanje že ima aktiven najem.',
            'conflicting_lease_id' => $existing->id,
        ]);

    expect($draft->fresh()->status)->toBe(LeaseStatus::Draft);
})->with([LeaseStatus::Active, LeaseStatus::InNotice]);

it('dovoli aktivacijo, če je prejšnji najem zaključen', function () {
    Lease::factory()->for($this->apartment)->withTenants()->create(['status' => LeaseStatus::Ended]);
    $draft = Lease::factory()->for($this->apartment)->withTenants()->create();

    $this->actingAs($this->landlord)->postJson("/api/leases/{$draft->id}/activate")->assertOk();
});

it('baza sama prepreči dva aktivna najema istega stanovanja', function () {
    Lease::factory()->for($this->apartment)->active()->create();

    expect(fn () => Lease::factory()->for($this->apartment)->active()->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('aktiviramo lahko samo osnutek', function () {
    $lease = Lease::factory()->for($this->apartment)->active()->withTenants()->create();

    $this->actingAs($this->landlord)->postJson("/api/leases/{$lease->id}/activate")->assertConflict();
});

it('najemnik najema ne more aktivirati', function () {
    $lease = Lease::factory()->for($this->apartment)->withTenants()->create();
    $tenant = $lease->tenants->first()->user;

    $this->actingAs($tenant)->postJson("/api/leases/{$lease->id}/activate")->assertForbidden();
});
