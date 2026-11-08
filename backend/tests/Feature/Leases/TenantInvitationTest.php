<?php

use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\User;
use App\Notifications\AddedToLease;
use App\Notifications\TenantInvitation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->building = Building::factory()->create();
    $this->apartment = Apartment::factory()->for($this->building)->create();
    $this->landlord = memberOf($this->building, Role::Landlord);
});

function createLeaseFor(array $tenant): void
{
    test()->actingAs(test()->landlord)->postJson('/api/apartments/'.test()->apartment->id.'/leases', [
        'starts_on' => '2027-05-01',
        'notice_period_days' => 30,
        'monthly_rent_cents' => 50000,
        'deposit_cents' => 100000,
        'tenants' => [[...$tenant, 'rent_share_cents' => 50000]],
    ])->assertCreated();
}

it('novemu najemniku ustvari račun in pošlje povabilo z žetonom', function () {
    createLeaseFor(['name' => 'Ana Kovač', 'email' => 'ana@example.com']);

    $user = User::where('email', 'ana@example.com')->firstOrFail();
    expect($user->hasRole($this->building, Role::Tenant))->toBeTrue();

    Notification::assertSentTo($user, TenantInvitation::class, function (TenantInvitation $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        // V URL-ju je samo žeton, ne e-naslov.
        return str_contains($url, '/povabilo/'.$notification->token) && ! str_contains($url, 'ana@example.com');
    });
});

it('obstoječemu uporabniku ne ustvari novega računa, ampak samo doda dostop', function () {
    $existing = User::factory()->create(['email' => 'ana@example.com']);

    createLeaseFor(['name' => 'Ana K.', 'email' => 'ANA@example.com']);

    expect(User::where('email', 'ana@example.com')->count())->toBe(1)
        ->and($existing->hasRole($this->building, Role::Tenant))->toBeTrue();
    Notification::assertSentTo($existing, AddedToLease::class);
    Notification::assertNotSentTo($existing, TenantInvitation::class);
});

it('najemnik z žetonom iz povabila nastavi geslo in se prijavi', function () {
    createLeaseFor(['name' => 'Ana Kovač', 'email' => 'ana@example.com']);
    $user = User::where('email', 'ana@example.com')->first();
    $token = Notification::sent($user, TenantInvitation::class)->first()->token;

    auth()->guard('web')->logout();

    $this->postJson('/api/invitations/accept', [
        'token' => $token,
        'email' => 'ana@example.com',
        'password' => 'novo-geslo-123',
        'password_confirmation' => 'novo-geslo-123',
    ])->assertOk();

    $this->postJson('/login', ['email' => 'ana@example.com', 'password' => 'novo-geslo-123'])->assertOk();
});

it('povezava povabila po 7 dneh ne velja več', function () {
    createLeaseFor(['name' => 'Ana Kovač', 'email' => 'ana@example.com']);
    $user = User::where('email', 'ana@example.com')->first();
    $token = Notification::sent($user, TenantInvitation::class)->first()->token;

    $this->travel(7)->days();
    $this->travel(1)->minutes();

    $this->postJson('/api/invitations/accept', [
        'token' => $token,
        'email' => 'ana@example.com',
        'password' => 'novo-geslo-123',
        'password_confirmation' => 'novo-geslo-123',
    ])->assertUnprocessable()->assertJsonValidationErrors('token');
});
