<?php

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Lease;

beforeEach(function () {
    $this->building = Building::factory()->create();
    $this->apartment = Apartment::factory()->for($this->building)->create();
    $this->landlord = memberOf($this->building, Role::Landlord);
});

it('na dan prenehanja najem zaključi in stanovanje označi kot prosto', function () {
    $lease = Lease::factory()->for($this->apartment)->withTenants()->create([
        'status' => LeaseStatus::InNotice,
        'notice_given_on' => '2027-03-01',
        'terminates_on' => '2027-03-31',
    ]);

    $this->travelTo('2027-03-30 23:00');
    $this->artisan('leases:end-due')->assertSuccessful();
    expect($lease->fresh()->status)->toBe(LeaseStatus::InNotice);

    $this->travelTo('2027-03-31 00:05');
    $this->artisan('leases:end-due')->assertSuccessful();

    $lease->refresh();
    expect($lease->status)->toBe(LeaseStatus::Ended)
        ->and($lease->tenants[0]->moved_out_on->toDateString())->toBe('2027-03-31');

    $this->actingAs($this->landlord)
        ->getJson("/api/buildings/{$this->building->id}/apartments")
        ->assertJsonPath('data.0.occupancy', 'vacant');
});

it('najem za določen čas brez odpovedi se zaključi na datum konca', function () {
    $lease = Lease::factory()->for($this->apartment)->active()->withTenants()->create(['ends_on' => '2027-06-30']);

    $this->travelTo('2027-06-30 00:05');
    $this->artisan('leases:end-due');

    expect($lease->fresh()->status)->toBe(LeaseStatus::Ended)
        ->and($lease->fresh()->terminates_on->toDateString())->toBe('2027-06-30');
});

it('najema za nedoločen čas in osnutkov ne zaključi', function () {
    $open = Lease::factory()->for($this->apartment)->active()->withTenants()->create();
    $draft = Lease::factory()->for(Apartment::factory()->for($this->building))->withTenants()->create(['ends_on' => '2020-01-01']);

    $this->artisan('leases:end-due');

    expect($open->fresh()->status)->toBe(LeaseStatus::Active)
        ->and($draft->fresh()->status)->toBe(LeaseStatus::Draft);
});

it('opravilo je v razporejevalniku', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('leases:end-due');
});
