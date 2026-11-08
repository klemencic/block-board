<?php

use App\Enums\ChargeType;
use App\Enums\LeaseStatus;
use App\Enums\Payer;
use App\Enums\RepairStatus;
use App\Enums\Role;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\RepairCategory;
use App\Models\RepairRequest;

beforeEach(function () {
    $this->travelTo('2026-10-21 10:00');
    $this->building = Building::factory()->create();
    $this->landlord = memberOf($this->building, Role::Landlord);
    $apt = fn (string $label, int $floor) => Apartment::factory()->for($this->building)->create(['label' => $label, 'floor' => $floor]);

    // 1A zaseden za nedoločen čas, 1B se izteka, 1C prost, 2A z zamudo, 2B v odpovednem roku.
    $this->a1 = $apt('1A', 0);
    $this->b1 = $apt('1B', 0);
    $this->c1 = $apt('1C', 0);
    $this->a2 = $apt('2A', 1);
    $this->b2 = $apt('2B', 1);

    Lease::factory()->for($this->a1)->withTenants()->create(['status' => LeaseStatus::Active]);
    Lease::factory()->for($this->b1)->withTenants()->create(['status' => LeaseStatus::Active, 'ends_on' => '2026-11-30']);
    $this->lease2a = Lease::factory()->for($this->a2)->withTenants([30000, 30000])->create(['status' => LeaseStatus::Active]);
    Lease::factory()->for($this->b2)->withTenants()->create([
        'status' => LeaseStatus::InNotice, 'notice_given_on' => '2026-10-16', 'terminates_on' => '2026-11-15',
    ]);
    // Zaključen najem v prostem stanovanju ne šteje.
    Lease::factory()->for($this->c1)->withTenants()->create(['status' => LeaseStatus::Ended]);

    $charge = fn (array $attrs) => Charge::create([
        'lease_tenant_id' => $this->lease2a->tenants[0]->id, 'type' => ChargeType::Rent, 'description' => 'Najemnina',
        'amount_cents' => 30000, 'period' => '2026-09-01', 'due_date' => '2026-09-08', ...$attrs,
    ]);
    $charge([]);                                                      // zamuja
    $charge(['period' => '2026-08-01', 'due_date' => '2026-08-08', 'paid_at' => '2026-08-05']); // plačano
    $charge(['period' => '2026-10-01', 'due_date' => '2026-10-30']); // rok še ni pretekel

    $category = RepairCategory::create(['name' => 'Elektrika', 'default_payer' => Payer::Landlord]);
    foreach ([[RepairStatus::Reported, $this->b1], [RepairStatus::Assigned, $this->b1], [RepairStatus::Closed, $this->b1], [RepairStatus::Rejected, $this->a2]] as [$status, $apartment]) {
        RepairRequest::create([
            'building_id' => $this->building->id, 'apartment_id' => $apartment->id, 'reported_by' => $this->landlord->id,
            'category_id' => $category->id, 'title' => 'x', 'description' => 'x', 'status' => $status,
            'default_payer' => Payer::Landlord, 'payer' => Payer::Landlord,
        ]);
    }
});

it('tabela prikaže vsa stanovanja s stanjem, najemniki in datumom konca ali prenehanja', function () {
    $rows = collect($this->actingAs($this->landlord)->getJson("/api/buildings/{$this->building->id}/overview")
        ->assertOk()->json('data.apartments'))->keyBy('label');

    expect($rows->keys()->all())->toBe(['1A', '1B', '1C', '2A', '2B'])
        ->and($rows['1A'])->toMatchArray(['floor' => 0, 'state' => 'occupied', 'end_type' => 'indefinite', 'end_date' => null])
        ->and($rows['1B'])->toMatchArray(['state' => 'occupied', 'end_type' => 'end', 'end_date' => '2026-11-30'])
        ->and($rows['1C'])->toMatchArray(['state' => 'vacant', 'tenants' => [], 'end_date' => null])
        ->and($rows['2A']['tenants'])->toHaveCount(2)
        ->and($rows['2B'])->toMatchArray(['state' => 'in_notice', 'end_type' => 'termination', 'end_date' => '2026-11-15']);
});

it('pri vsakem stanovanju prikaže vsoto zamud in število odprtih prijav (brez zaključenih in zavrnjenih)', function () {
    $rows = collect($this->actingAs($this->landlord)->getJson("/api/buildings/{$this->building->id}/overview")
        ->json('data.apartments'))->keyBy('label');

    expect($rows['2A'])->toMatchArray(['overdue_cents' => 30000, 'overdue_count' => 1, 'open_repairs' => 0])
        ->and($rows['1B'])->toMatchArray(['overdue_cents' => 0, 'open_repairs' => 2])
        ->and($rows['1A']['overdue_cents'])->toBe(0);
});

it('najem, ki se konča v naslednjih 60 dneh, ima oznako Izteka se', function () {
    $rows = collect($this->actingAs($this->landlord)->getJson("/api/buildings/{$this->building->id}/overview")
        ->json('data.apartments'))->keyBy('label');

    expect($rows['1B']['expiring'])->toBeTrue()
        ->and($rows['1A']['expiring'])->toBeFalse();

    $this->travelTo('2026-09-30 10:00'); // 61 dni pred koncem
    $rows = collect($this->actingAs($this->landlord)->getJson("/api/buildings/{$this->building->id}/overview")->json('data.apartments'))->keyBy('label');
    expect($rows['1B']['expiring'])->toBeFalse();
});

it('povzetek prikaže zasedenost, skupne zamude in odprte prijave', function () {
    $summary = $this->actingAs($this->landlord)->getJson("/api/buildings/{$this->building->id}/overview")->json('data.summary');

    expect($summary)->toMatchArray([
        'apartments_total' => 5,
        'apartments_occupied' => 4,
        'overdue_cents' => 30000,
        'overdue_apartments' => 1,
        'overdue_charges' => 1,
        'open_repairs' => 2,
        'repairs_awaiting_confirmation' => 1,
        'expiring_leases' => 1,
        'counts' => ['occupied' => 3, 'in_notice' => 1, 'vacant' => 1],
    ]);
});

it('podrobnosti stanovanja vrnejo trenutni najem, zamude in odprte prijave', function () {
    $this->actingAs($this->landlord)->getJson("/api/apartments/{$this->a2->id}")
        ->assertOk()
        ->assertJsonPath('data.label', '2A')
        ->assertJsonPath('data.current_lease.id', $this->lease2a->id)
        ->assertJsonCount(1, 'data.overdue_charges');
});

it('pregled stavbe vidi samo lastnik', function () {
    $tenant = $this->lease2a->tenants[0]->user;
    \App\Models\Membership::create(['user_id' => $tenant->id, 'building_id' => $this->building->id, 'role' => Role::Tenant]);

    $this->actingAs($tenant)->getJson("/api/buildings/{$this->building->id}/overview")->assertForbidden();
    $this->actingAs($tenant)->getJson("/api/apartments/{$this->a2->id}")->assertForbidden();
});

it('številka ob meniju Okvare je število odprtih prijav', function () {
    $this->actingAs($this->landlord)->getJson("/api/buildings/{$this->building->id}/badges")
        ->assertJsonPath('data./okvare', 2);
});
