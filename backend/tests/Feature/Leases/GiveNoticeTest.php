<?php

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Membership;
use App\Notifications\LeaseNoticeGiven;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2027-03-01 10:00');
    $this->building = Building::factory()->create();
    $this->apartment = Apartment::factory()->for($this->building)->create();
    $this->landlord = memberOf($this->building, Role::Landlord);
});

function leaseWithTenants(array $shares, array $attributes = []): Lease
{
    $lease = Lease::factory()->for(test()->apartment)->active()->withTenants($shares)->create($attributes);
    foreach ($lease->tenants as $tenant) {
        Membership::create(['user_id' => $tenant->user_id, 'building_id' => test()->building->id, 'role' => Role::Tenant]);
    }

    return $lease->load('tenants.user');
}

it('najemnik pred oddajo vidi datum prenehanja', function () {
    $lease = leaseWithTenants([60000]);

    $this->actingAs($lease->tenants[0]->user)
        ->getJson("/api/leases/{$lease->id}")
        ->assertJsonPath('data.notice_preview_terminates_on', '2027-03-31');
});

it('pri enem najemniku odpoved takoj velja in izračuna datum prenehanja', function () {
    $lease = leaseWithTenants([60000]);

    $this->actingAs($lease->tenants[0]->user)
        ->postJson("/api/leases/{$lease->id}/give-notice")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_notice')
        ->assertJsonPath('data.notice_given_on', '2027-03-01')
        ->assertJsonPath('data.terminates_on', '2027-03-31');
});

it('pri več sostanovalcih najem ostane aktiven, dokler odpovedi ne potrdijo vsi', function () {
    $lease = leaseWithTenants([30000, 30000]);
    [$first, $second] = [$lease->tenants[0]->user, $lease->tenants[1]->user];

    $response = $this->actingAs($first)->postJson("/api/leases/{$lease->id}/give-notice")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    // Sostanovalci vidijo, kdo odpovedi še ni potrdil.
    expect($response->json('data.tenants.0.notice_confirmed_at'))->not->toBeNull()
        ->and($response->json('data.tenants.1.notice_confirmed_at'))->toBeNull();
    Notification::assertNothingSent();

    // Datum odpovedi je dan zadnje potrditve.
    $this->travelTo('2027-03-05 09:00');
    $this->actingAs($second)->postJson("/api/leases/{$lease->id}/give-notice")
        ->assertJsonPath('data.status', 'in_notice')
        ->assertJsonPath('data.notice_given_on', '2027-03-05')
        ->assertJsonPath('data.terminates_on', '2027-04-04');
});

it('ponovna potrditev istega najemnika ne spremeni časa potrditve', function () {
    $lease = leaseWithTenants([30000, 30000]);
    $user = $lease->tenants[0]->user;

    $this->actingAs($user)->postJson("/api/leases/{$lease->id}/give-notice");
    $confirmedAt = $lease->tenants[0]->fresh()->notice_confirmed_at;

    $this->travel(2)->days();
    $this->actingAs($user)->postJson("/api/leases/{$lease->id}/give-notice")->assertOk();

    expect($lease->tenants[0]->fresh()->notice_confirmed_at->equalTo($confirmedAt))->toBeTrue();
});

it('lastnik in vsi sostanovalci prejmejo e-pošto z datumoma', function () {
    $lease = leaseWithTenants([30000, 30000]);

    foreach ($lease->tenants as $tenant) {
        $this->actingAs($tenant->user)->postJson("/api/leases/{$lease->id}/give-notice");
    }

    Notification::assertSentTo(
        [$this->landlord, $lease->tenants[0]->user, $lease->tenants[1]->user],
        LeaseNoticeGiven::class,
        function (LeaseNoticeGiven $notification, array $channels, object $notifiable) {
            $lines = implode(' ', $notification->toMail($notifiable)->introLines);

            return str_contains($lines, 'Datum odpovedi: 1. 3. 2027')
                && str_contains($lines, 'Datum prenehanja: 31. 3. 2027');
        }
    );
});

it('odpoved je mogoča samo pri aktivnem najemu', function (LeaseStatus $status) {
    $lease = leaseWithTenants([60000], ['status' => $status]);

    $this->actingAs($lease->tenants[0]->user)
        ->postJson("/api/leases/{$lease->id}/give-notice")
        ->assertConflict();
})->with([LeaseStatus::Draft, LeaseStatus::InNotice, LeaseStatus::Ended]);

it('odpovedi ne more podati nekdo, ki ni najemnik na najemu', function () {
    $lease = leaseWithTenants([60000]);
    $other = memberOf($this->building, Role::Tenant);

    $this->actingAs($other)->postJson("/api/leases/{$lease->id}/give-notice")->assertForbidden();
    $this->actingAs($this->landlord)->postJson("/api/leases/{$lease->id}/give-notice")->assertForbidden();
});
