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
