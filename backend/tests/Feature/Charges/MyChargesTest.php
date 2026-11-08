<?php

use App\Enums\ChargeType;
use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\LeaseTenant;

beforeEach(function () {
    $this->building = Building::factory()->create();
    $this->lease = Lease::factory()
        ->for(Apartment::factory()->for($this->building))
        ->withTenants([30000, 20000])
        ->create(['status' => LeaseStatus::Active]);
    [$this->me, $this->roommate] = [$this->lease->tenants[0], $this->lease->tenants[1]];
    $this->landlord = memberOf($this->building, Role::Landlord);
});

function charge(LeaseTenant $tenant, array $attributes = []): Charge
{
    return Charge::create([
        'lease_tenant_id' => $tenant->id,
        'type' => ChargeType::Rent,
        'description' => 'Najemnina',
        'amount_cents' => 30000,
        'period' => '2027-05-01',
        'due_date' => '2027-05-08',
        ...$attributes,
    ]);
}

it('prikaže obveznosti najemnika po mesecih z vsoto vseh in odprtih obveznosti', function () {
    $this->travelTo('2027-05-05 10:00');
    charge($this->me, ['amount_cents' => 30000]);
    charge($this->me, ['type' => ChargeType::Utility, 'description' => 'Elektrika april', 'amount_cents' => 3600, 'paid_at' => '2027-05-03']);
    charge($this->me, ['period' => '2027-04-01', 'due_date' => '2027-04-08', 'paid_at' => '2027-04-07']);

    $response = $this->actingAs($this->me->user)->getJson('/api/me/charges')->assertOk();

    $months = $response->json('data');
    expect($months)->toHaveCount(2)
        ->and($months[0]['period'])->toBe('2027-05-01')
        ->and($months[0]['total_cents'])->toBe(33600)
        ->and($months[0]['open_cents'])->toBe(30000)
        ->and($months[0]['charges'][0])->toMatchArray([
            'type' => 'rent', 'type_label' => 'najemnina', 'description' => 'Najemnina',
            'amount_cents' => 30000, 'due_date' => '2027-05-08', 'status' => 'open',
        ])
        ->and($months[1]['period'])->toBe('2027-04-01');
});

it('najemnik vidi samo svoje obveznosti, ne sostanovalčevih ali tujih', function () {
    charge($this->me);
    charge($this->roommate, ['amount_cents' => 20000]);
    $other = Lease::factory()->for(Apartment::factory()->for($this->building))->withTenants()->create();
    charge($other->tenants[0]);

    $charges = collect($this->actingAs($this->me->user)->getJson('/api/me/charges')->json('data'))
        ->flatMap(fn ($m) => $m['charges']);

    expect($charges)->toHaveCount(1)
        ->and($charges[0]['amount_cents'])->toBe(30000);

    $foreign = Charge::where('lease_tenant_id', $this->roommate->id)->first();
    $this->actingAs($this->me->user)->getJson("/api/charges/{$foreign->id}")->assertForbidden();
    $this->actingAs($this->landlord)->getJson("/api/charges/{$foreign->id}")->assertOk();
});

it('neplačana obveznost s pretečenim rokom zamuja in prikaže število dni', function () {
    $charge = charge($this->me, ['due_date' => '2027-05-08']);

    $this->travelTo('2027-05-08 12:00');
    $this->actingAs($this->me->user)->getJson("/api/charges/{$charge->id}")
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.days_overdue', 0);

    $this->travelTo('2027-05-20 12:00');
    $this->actingAs($this->me->user)->getJson("/api/charges/{$charge->id}")
        ->assertJsonPath('data.status', 'overdue')
        ->assertJsonPath('data.days_overdue', 12);
});

it('plačana obveznost nikoli ne zamuja', function () {
    $charge = charge($this->me, ['due_date' => '2027-05-08', 'paid_at' => '2027-05-15 10:00']);

    $this->travelTo('2027-06-20 12:00');
    $this->actingAs($this->me->user)->getJson("/api/charges/{$charge->id}")
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.days_overdue', 0);
});

it('današnji dan določa časovni pas stavbe (Europe/Ljubljana)', function () {
    config(['app.timezone' => 'Europe/Ljubljana']);
    date_default_timezone_set('Europe/Ljubljana');
    $charge = charge($this->me, ['due_date' => '2027-05-08']);

    // 8. 5. ob 23:30 v Ljubljani (21:30 UTC) rok še ni pretekel.
    $this->travelTo(\Carbon\Carbon::parse('2027-05-08 21:30', 'UTC'));
    expect($charge->fresh()->status())->toBe('open');

    // 9. 5. ob 00:30 v Ljubljani (8. 5. 22:30 UTC) obveznost zamuja 1 dan.
    $this->travelTo(\Carbon\Carbon::parse('2027-05-08 22:30', 'UTC'));
    expect($charge->fresh()->status())->toBe('overdue')
        ->and($charge->fresh()->daysOverdue())->toBe(1);
});

it('najemnina nima izvora, manjkajoč izvor je napaka 404 s sporočilom', function () {
    $rent = charge($this->me);
    $this->actingAs($this->me->user)->getJson("/api/charges/{$rent->id}/source")
        ->assertNotFound()
        ->assertJsonPath('message', 'Ta obveznost nima povezanega izvora.');

    $utility = charge($this->me, ['type' => ChargeType::Utility, 'bill_share_id' => null]);
    $this->actingAs($this->me->user)->getJson("/api/charges/{$utility->id}/source")
        ->assertNotFound()
        ->assertJsonPath('message', 'Izvor obveznosti ni bil najden.');
});

it('do izvora tuje obveznosti najemnik nima dostopa', function () {
    $foreign = charge($this->roommate, ['type' => ChargeType::Utility]);

    $this->actingAs($this->me->user)->getJson("/api/charges/{$foreign->id}/source")->assertForbidden();
});
