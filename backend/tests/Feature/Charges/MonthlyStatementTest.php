<?php

use App\Enums\ChargeType;
use App\Enums\LeaseStatus;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Charge;
use App\Models\Lease;
use App\Notifications\MonthlyStatement;
use App\Services\ChargeGenerator;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->building = Building::factory()->create(['rent_due_day' => 8]);
    $this->lease = Lease::factory()
        ->for(Apartment::factory()->for($this->building))
        ->withTenants([30000, 20000])
        ->create(['starts_on' => '2027-01-01', 'status' => LeaseStatus::Active]);
});

it('prvi dan v mesecu najemniku pošlje najemnino in obveznosti iz preteklega meseca s skupnim zneskom', function () {
    $jan = $this->lease->tenants[0];
    // Delež položnice, vnesen aprila, gre v majski obračun.
    Charge::create([
        'lease_tenant_id' => $jan->id, 'type' => ChargeType::Utility, 'description' => 'Elektrika april',
        'amount_cents' => 3600, 'period' => '2027-05-01', 'due_date' => '2027-05-08',
    ]);

    $this->travelTo('2027-05-01 07:00');
    $this->artisan('charges:generate --statements')->assertSuccessful();

    Notification::assertSentTo($jan->user, MonthlyStatement::class, function (MonthlyStatement $n) use ($jan) {
        $text = implode("\n", $n->toMail($jan->user)->introLines);

        return $n->charges->count() === 2
            && $n->total() === 33600
            && str_contains($text, 'Najemnina maj 2027: 300,00 €')
            && str_contains($text, 'Elektrika april: 36,00 €')
            && str_contains($text, 'Skupaj: 336,00 €')
            && str_contains($text, 'Rok plačila: 8. 5. 2027');
    });
});

it('povzetek vsebuje samo obveznosti tega najemnika', function () {
    $this->travelTo('2027-05-01 07:00');
    $this->artisan('charges:generate --statements');

    [$first, $second] = [$this->lease->tenants[0], $this->lease->tenants[1]];

    Notification::assertSentTo($second->user, MonthlyStatement::class, fn (MonthlyStatement $n) => $n->charges->every(fn ($c) => $c->lease_tenant_id === $second->id)
        && $n->total() === 20000);
    Notification::assertSentToTimes($first->user, MonthlyStatement::class, 1);
});

it('ponovno pošiljanje ne podvoji e-pošte', function () {
    $this->travelTo('2027-05-01 07:00');
    $generator = app(ChargeGenerator::class);
    $generator->generate();

    expect($generator->sendStatements())->toBe(2)
        ->and($generator->sendStatements())->toBe(0);

    Notification::assertSentToTimes($this->lease->tenants[0]->user, MonthlyStatement::class, 1);
});

it('je v razporejevalniku za prvi dan v mesecu', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('charges:generate --statements');
});
