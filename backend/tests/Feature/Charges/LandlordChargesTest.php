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
    $this->travelTo('2027-05-20 10:00');
    $this->building = Building::factory()->create(['rent_due_day' => 8]);
    $this->landlord = memberOf($this->building, Role::Landlord);
    $this->a2 = Apartment::factory()->for($this->building)->create(['label' => '2A']);
    $this->a3 = Apartment::factory()->for($this->building)->create(['label' => '3A']);
    $this->lease2 = Lease::factory()->for($this->a2)->withTenants([30000])->create(['status' => LeaseStatus::Active]);
    $this->lease3 = Lease::factory()->for($this->a3)->withTenants([33000])->create(['status' => LeaseStatus::Active]);
    $this->t2 = $this->lease2->tenants[0];
    $this->t3 = $this->lease3->tenants[0];
});

function rent(LeaseTenant $tenant, string $period, array $attributes = []): Charge
{
    return Charge::create([
        'lease_tenant_id' => $tenant->id, 'type' => ChargeType::Rent, 'description' => 'Najemnina',
        'amount_cents' => $tenant->rent_share_cents, 'period' => $period,
        'due_date' => substr($period, 0, 8).'08', ...$attributes,
    ]);
}

it('lastnik označi obveznost kot plačano z današnjim datumom', function () {
    $charge = rent($this->t2, '2027-05-01');

    $this->actingAs($this->landlord)->postJson("/api/charges/{$charge->id}/mark-paid")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.paid_at', '2027-05-20');
});

it('lastnik lahko vnese pretekli datum plačila, ne pa prihodnjega', function () {
    $charge = rent($this->t2, '2027-05-01');

    $this->actingAs($this->landlord)->postJson("/api/charges/{$charge->id}/mark-paid", ['paid_on' => '2027-05-21'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['paid_on' => 'Datum plačila ne sme biti v prihodnosti.']);

    $this->actingAs($this->landlord)->postJson("/api/charges/{$charge->id}/mark-paid", ['paid_on' => '2027-05-07'])
        ->assertOk()
        ->assertJsonPath('data.paid_at', '2027-05-07');
});

it('plačane obveznosti ni mogoče ponovno označiti', function () {
    $charge = rent($this->t2, '2027-05-01', ['paid_at' => '2027-05-07']);

    $this->actingAs($this->landlord)->postJson("/api/charges/{$charge->id}/mark-paid")->assertConflict();
});

it('najemnik plačila ne more označiti', function () {
    $charge = rent($this->t2, '2027-05-01');

    $this->actingAs($this->t2->user)->postJson("/api/charges/{$charge->id}/mark-paid")->assertForbidden();
    expect($charge->fresh()->paid_at)->toBeNull();
});

it('izdane obveznosti ni mogoče urejati ali izbrisati', function () {
    $charge = rent($this->t2, '2027-05-01');

    // API nima končnih točk za urejanje ali brisanje.
    $this->actingAs($this->landlord)->patchJson("/api/charges/{$charge->id}", ['amount_cents' => 1])->assertStatus(405);
    $this->actingAs($this->landlord)->deleteJson("/api/charges/{$charge->id}")->assertStatus(405);

    // Tudi model zavrne spremembo zneska, opisa in roka ter brisanje.
    expect(fn () => $charge->update(['amount_cents' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $charge->fresh()->update(['description' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $charge->fresh()->update(['due_date' => '2027-06-01']))->toThrow(LogicException::class)
        ->and(fn () => $charge->fresh()->delete())->toThrow(LogicException::class);
    expect($charge->fresh()->amount_cents)->toBe(30000);
});

it('popravek je nova obveznost vrste drugo, negativen znesek je dobropis', function () {
    $this->actingAs($this->landlord)->postJson("/api/buildings/{$this->building->id}/charges", [
        'lease_tenant_id' => $this->t2->id,
        'description' => 'Dobropis: preveč zaračunana elektrika',
        'amount_cents' => -1250,
    ])->assertCreated()
        ->assertJsonPath('data.type', 'other')
        ->assertJsonPath('data.amount_cents', -1250)
        ->assertJsonPath('data.period', '2027-05-01')
        ->assertJsonPath('data.due_date', '2027-05-08');
});

it('popravka ni mogoče dodati najemniku druge stavbe ali z ničelnim zneskom', function () {
    $foreign = Lease::factory()->withTenants()->create()->tenants[0];

    $this->actingAs($this->landlord)->postJson("/api/buildings/{$this->building->id}/charges", [
        'lease_tenant_id' => $foreign->id, 'description' => 'x', 'amount_cents' => 100,
    ])->assertUnprocessable();

    $this->actingAs($this->landlord)->postJson("/api/buildings/{$this->building->id}/charges", [
        'lease_tenant_id' => $this->t2->id, 'description' => 'x', 'amount_cents' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors('amount_cents');

    $this->actingAs($this->t2->user)->postJson("/api/buildings/{$this->building->id}/charges", [
        'lease_tenant_id' => $this->t2->id, 'description' => 'x', 'amount_cents' => -100,
    ])->assertForbidden();
});

it('seznam obveznosti stavbe filtrira po mesecu, stanovanju in stanju', function () {
    rent($this->t2, '2027-04-01');                                   // zamuja
    rent($this->t2, '2027-05-01', ['paid_at' => '2027-05-05']);      // plačano
    rent($this->t3, '2027-05-01');                                   // zamuja (rok 8. 5.)
    rent($this->t3, '2027-06-01');                                   // odprto, rok še ni pretekel
    $url = "/api/buildings/{$this->building->id}/charges";

    $ids = fn ($response) => collect($response->json('data'))->pluck('id')->sort()->values()->all();

    expect($this->actingAs($this->landlord)->getJson($url)->json('data'))->toHaveCount(4);
    expect($this->actingAs($this->landlord)->getJson("$url?period=2027-05")->json('data'))->toHaveCount(2);
    expect($this->actingAs($this->landlord)->getJson("$url?apartment_id={$this->a3->id}")->json('data'))->toHaveCount(2);
    expect($this->actingAs($this->landlord)->getJson("$url?status=paid")->json('data'))->toHaveCount(1);

    // Filter Zamuja: samo neplačane obveznosti s pretečenim rokom.
    $overdue = $this->actingAs($this->landlord)->getJson("$url?status=overdue")->json('data');
    expect($overdue)->toHaveCount(2)
        ->and(collect($overdue)->pluck('status')->unique()->all())->toBe(['overdue'])
        ->and($overdue[0])->toHaveKeys(['tenant', 'apartment', 'amount_cents', 'due_date', 'status']);
});

it('seznama obveznosti stavbe najemnik ne vidi', function () {
    $this->actingAs($this->t2->user)->getJson("/api/buildings/{$this->building->id}/charges")->assertForbidden();
});
