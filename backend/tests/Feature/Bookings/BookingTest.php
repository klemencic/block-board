<?php

use App\Enums\ChargeType;
use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Building;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\Membership;
use App\Models\SharedSpace;
use Illuminate\Database\QueryException;

beforeEach(function () {
    // Sreda, 21. 10. 2026 (kot v wireframu WF-02).
    $this->travelTo('2026-10-21 15:40');
    $this->building = Building::factory()->create(['rent_due_day' => 8]);
    $this->landlord = memberOf($this->building, Role::Landlord);
    $this->space = SharedSpace::create([
        'building_id' => $this->building->id, 'name' => 'Skupna soba', 'price_per_hour_cents' => 500,
        'open_from' => '08:00', 'open_to' => '22:00', 'max_hours_per_week' => 6, 'free_cancel_hours' => 24,
    ]);

    $this->lease = Lease::factory()->for(Apartment::factory()->for($this->building)->state(['label' => '2C']))
        ->withTenants([30000, 30000])->create(['status' => LeaseStatus::Active]);
    [$this->jan, $this->nik] = [$this->lease->tenants[0], $this->lease->tenants[1]];
    $this->other = Lease::factory()->for(Apartment::factory()->for($this->building)->state(['label' => '3B']))
        ->withTenants()->create(['status' => LeaseStatus::Active])->tenants[0];

    foreach ([$this->jan, $this->nik, $this->other] as $t) {
        Membership::create(['user_id' => $t->user_id, 'building_id' => $this->building->id, 'role' => Role::Tenant]);
    }
});

function book($tenant, string $start, string $end): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($tenant->user)->postJson('/api/shared-spaces/'.test()->space->id.'/bookings', [
        'starts_at' => $start, 'ends_at' => $end,
    ]);
}

it('rezervira termin, shrani ceno in jo doda k obračunu meseca po terminu', function () {
    // 3 h po 5,00 € v oktobru → obveznost 15,00 € v novembrskem obračunu.
    book($this->jan, '2026-10-22 18:00', '2026-10-22 21:00')
        ->assertCreated()
        ->assertJsonPath('data.fee_cents', 1500)
        ->assertJsonPath('data.charge_period_label', 'november');

    $charge = Charge::sole();
    expect($charge->type)->toBe(ChargeType::Booking)
        ->and($charge->amount_cents)->toBe(1500)
        ->and($charge->period->toDateString())->toBe('2026-11-01')
        ->and($charge->booking_id)->toBe(Booking::sole()->id)
        ->and($charge->description)->toBe('Skupna soba 22. 10. 2026 18:00–21:00');
});

it('cena je shranjena ob rezervaciji in se ne spremeni s cenikom', function () {
    book($this->jan, '2026-10-22 18:00', '2026-10-22 20:00')->assertCreated();
    $this->space->update(['price_per_hour_cents' => 900]);

    expect(Booking::sole()->fee_cents)->toBe(1000);
});

it('pri brezplačnem prostoru obveznost ne nastane', function () {
    $this->space->update(['price_per_hour_cents' => 0]);

    book($this->jan, '2026-10-22 18:00', '2026-10-22 19:30')->assertCreated()->assertJsonPath('data.fee_cents', 0);
    expect(Charge::count())->toBe(0);
});

it('termin se začne in konča ob polni ali pol ure', function () {
    book($this->jan, '2026-10-22 18:15', '2026-10-22 19:00')->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at' => 'Rezervacija se začne in konča ob polni ali pol ure.']);
    book($this->jan, '2026-10-22 18:00', '2026-10-22 19:45')->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    book($this->jan, '2026-10-22 18:30', '2026-10-22 19:00')->assertCreated();
});

it('termin mora biti znotraj obratovalnega časa in se ne sme začeti v preteklosti', function () {
    book($this->jan, '2026-10-22 07:30', '2026-10-22 09:00')->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    book($this->jan, '2026-10-22 21:00', '2026-10-22 22:30')->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    book($this->jan, '2026-10-21 15:00', '2026-10-21 16:00')->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at' => 'Termin se ne sme začeti v preteklosti.']);
    book($this->jan, '2026-10-22 21:00', '2026-10-22 22:00')->assertCreated();
});

it('prekrivanje z nepreklicano rezervacijo zavrne s sporočilom »Termin je že zaseden.«', function () {
    book($this->other, '2026-10-22 17:00', '2026-10-22 18:30')->assertCreated();

    book($this->jan, '2026-10-22 18:00', '2026-10-22 19:00')->assertConflict()
        ->assertJsonPath('message', 'Termin je že zaseden.');

    // Stik brez prekrivanja je dovoljen.
    book($this->jan, '2026-10-22 18:30', '2026-10-22 19:00')->assertCreated();
});

it('preklicana rezervacija termina ne zaseda', function () {
    book($this->other, '2026-10-22 17:00', '2026-10-22 18:00')->assertCreated();
    Booking::sole()->update(['status' => 'cancelled', 'cancelled_at' => now()]);

    book($this->jan, '2026-10-22 17:00', '2026-10-22 18:00')->assertCreated();
});

it('baza sama prepreči prekrivanje, tudi mimo aplikacijske kontrole (sočasne zahteve)', function () {
    $row = ['shared_space_id' => $this->space->id, 'lease_tenant_id' => $this->jan->id, 'fee_cents' => 0];
    Booking::create([...$row, 'starts_at' => '2026-10-23 10:00', 'ends_at' => '2026-10-23 12:00']);

    expect(fn () => Booking::create([...$row, 'lease_tenant_id' => $this->other->id, 'starts_at' => '2026-10-23 11:30', 'ends_at' => '2026-10-23 13:00']))
        ->toThrow(QueryException::class);
});

it('upošteva tedensko omejitev vseh sostanovalcev in izpiše, koliko ur je še na voljo', function () {
    // Nik je v ponedeljek že rezerviral 2 h (teden 19.–25. 10.), omejitev je 6 h.
    Booking::create(['shared_space_id' => $this->space->id, 'lease_tenant_id' => $this->nik->id, 'fee_cents' => 1000,
        'starts_at' => '2026-10-19 18:00', 'ends_at' => '2026-10-19 20:00']);
    book($this->jan, '2026-10-22 18:00', '2026-10-22 21:00')->assertCreated(); // skupaj 5 h

    book($this->jan, '2026-10-24 10:00', '2026-10-24 12:00')->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_at' => 'Rezervacija presega tedensko omejitev stanovanja (6 h). Ta teden je na voljo še 1 h.']);

    book($this->nik, '2026-10-24 10:00', '2026-10-24 11:00')->assertCreated();

    // Naslednji teden se omejitev ponastavi.
    book($this->jan, '2026-10-26 10:00', '2026-10-26 13:00')->assertCreated();
});

it('koledar prikaže zasedene termine tedna brez imen drugih najemnikov', function () {
    book($this->other, '2026-10-22 17:00', '2026-10-22 18:00')->assertCreated();
    // Sostanovalčeva rezervacija iz ponedeljka (že v preteklosti, zato neposredno v bazo).
    Booking::create(['shared_space_id' => $this->space->id, 'lease_tenant_id' => $this->nik->id, 'fee_cents' => 0,
        'starts_at' => '2026-10-19 18:00', 'ends_at' => '2026-10-19 20:00']);

    $data = $this->actingAs($this->jan->user)
        ->getJson("/api/shared-spaces/{$this->space->id}/availability?week=2026-10-21")
        ->assertOk()->json('data');

    expect($data['week_start'])->toBe('2026-10-19')
        ->and($data['week_end'])->toBe('2026-10-25')
        ->and($data['space']['open_from'])->toBe('08:00')
        ->and($data['usage'])->toMatchArray(['used_minutes' => 120, 'max_minutes' => 360, 'available_minutes' => 240, 'apartment' => '2C']);

    $own = collect($data['bookings'])->firstWhere('own', true);
    $foreign = collect($data['bookings'])->firstWhere('own', false);
    expect($own['apartment'])->toBe('2C')
        ->and($foreign['apartment'])->toBeNull()
        ->and(json_encode($data))->not->toContain($this->other->user->name);
});

it('lastnik vidi koledar, rezervirati pa ne more', function () {
    $this->actingAs($this->landlord)->getJson("/api/shared-spaces/{$this->space->id}/availability")
        ->assertOk()->assertJsonPath('data.can_book', false);

    $this->actingAs($this->landlord)->postJson("/api/shared-spaces/{$this->space->id}/bookings", [
        'starts_at' => '2026-10-22 18:00', 'ends_at' => '2026-10-22 19:00',
    ])->assertForbidden();
});

it('najemnik ob obveznosti vidi rezervacijo kot izvor', function () {
    book($this->jan, '2026-10-22 18:00', '2026-10-22 21:00');
    $charge = Charge::sole();

    $this->actingAs($this->jan->user)->getJson("/api/charges/{$charge->id}/source")
        ->assertOk()
        ->assertJsonPath('data.type', 'booking')
        ->assertJsonPath('data.data.space_name', 'Skupna soba')
        ->assertJsonPath('data.data.hours', 3)
        ->assertJsonPath('data.data.price_per_hour_cents', 500)
        ->assertJsonPath('data.data.fee_cents', 1500);
});
