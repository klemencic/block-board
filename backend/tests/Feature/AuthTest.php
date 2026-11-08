<?php

use App\Enums\Role;
use App\Models\Building;
use App\Models\User;

it('prijavi uporabnika s pravilnim geslom', function () {
    $user = User::factory()->create(['password' => 'geslo123']);

    $this->postJson('/login', ['email' => $user->email, 'password' => 'geslo123'])
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('zavrne napačno geslo', function () {
    $user = User::factory()->create(['password' => 'geslo123']);

    $this->postJson('/login', ['email' => $user->email, 'password' => 'napacno'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('vrne prijavljenega uporabnika z vlogami po stavbah', function () {
    $building = Building::factory()->create(['name' => 'Stavba Lipa']);
    $user = memberOf($building, Role::Landlord);

    $this->actingAs($user)->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.memberships.0.building.name', 'Stavba Lipa')
        ->assertJsonPath('data.memberships.0.role', 'landlord');
});

it('neprijavljenemu uporabniku vrne 401', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
