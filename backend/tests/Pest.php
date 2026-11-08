<?php

use App\Enums\Role;
use App\Models\Building;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * Ustvari uporabnika z vlogo v podani stavbi.
 */
function memberOf(Building $building, Role $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    Membership::create(['user_id' => $user->id, 'building_id' => $building->id, 'role' => $role]);

    return $user;
}

/**
 * Stavba s prijavo okvare v stanju »prijavljeno« (US-09 do US-12):
 * lastnik, dva sostanovalca stanovanja 2C (prijavitelj je prvi) in izvajalec.
 */
function repairFixture(string $payer = 'landlord'): object
{
    $building = Building::factory()->create(['rent_due_day' => 8]);
    $landlord = memberOf($building, Role::Landlord);
    $contractor = memberOf($building, Role::Contractor, ['name' => 'Vodoinštalacije Breznik', 'phone' => '041 222 333']);
    $category = \App\Models\RepairCategory::create(['name' => 'Ogrevanje in topla voda', 'default_payer' => $payer]);

    $lease = \App\Models\Lease::factory()
        ->for(\App\Models\Apartment::factory()->for($building)->state(['label' => '2C', 'floor' => 1]))
        ->withTenants([30000, 30000])
        ->create(['status' => \App\Enums\LeaseStatus::Active]);
    foreach ($lease->tenants as $tenant) {
        Membership::create(['user_id' => $tenant->user_id, 'building_id' => $building->id, 'role' => Role::Tenant]);
    }
    $reporter = $lease->tenants[0]->user;
    $reporter->update(['phone' => '040 123 456']);

    $repair = app(\App\Services\RepairWorkflow::class)->report($building, $reporter, [
        'title' => 'Bojler ne greje vode',
        'description' => 'Od sinoči teče samo hladna voda.',
        'category_id' => $category->id,
        'apartment_id' => $lease->apartment_id,
    ]);

    return (object) compact('building', 'landlord', 'contractor', 'category', 'lease', 'reporter', 'repair');
}
