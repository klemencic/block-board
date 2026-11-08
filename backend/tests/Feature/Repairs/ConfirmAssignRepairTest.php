<?php

use App\Enums\RepairStatus;
use App\Enums\Role;
use App\Models\RepairEvent;
use App\Notifications\RepairAssigned;
use App\Notifications\RepairStatusChanged;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->f = repairFixture();
    $this->url = '/api/repair-requests/'.$this->f->repair->id;
});

it('lastnik potrdi prijavo, kar ustvari dogodek reported → confirmed', function () {
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $event = RepairEvent::where('kind', 'status')->latest('id')->first();
    expect($event->from_status)->toBe('reported')
        ->and($event->to_status)->toBe('confirmed')
        ->and($event->user_id)->toBe($this->f->landlord->id);
});

it('zavrnitev zahteva obrazložitev, ki jo najemnik vidi v časovnici', function () {
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/reject", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason' => 'Pri zavrnitvi je obrazložitev obvezna.']);

    $this->actingAs($this->f->landlord)->postJson("{$this->url}/reject", ['reason' => 'Okvara je posledica nepravilne uporabe.'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    $timeline = $this->actingAs($this->f->reporter)->getJson($this->url)->json('data.timeline');
    expect(collect($timeline)->last())->toMatchArray([
        'from_status' => 'reported', 'to_status' => 'rejected', 'note' => 'Okvara je posledica nepravilne uporabe.',
    ]);
});

it('neveljavnih prehodov ni mogoče izvesti', function () {
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/reject", ['reason' => 'x'])->assertOk();

    $this->actingAs($this->f->landlord)->postJson("{$this->url}/confirm")
        ->assertConflict()->assertJsonPath('message', 'Prehod iz stanja »Zavrnjeno« v »Potrjeno« ni dovoljen.');
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/assign", ['contractor_id' => $this->f->contractor->id])->assertConflict();
});

it('sprememba plačnika se zabeleži s prejšnjo in novo vrednostjo, avtorjem in časom', function () {
    $this->travelTo('2026-10-20 09:30');
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/payer", ['payer' => 'tenant'])
        ->assertOk()->assertJsonPath('data.payer', 'tenant');

    $event = RepairEvent::where('kind', 'payer')->latest('id')->first();
    expect($event->meta)->toBe(['from' => 'landlord', 'payer' => 'tenant'])
        ->and($event->note)->toBe('lastnik → najemnik')
        ->and($event->user_id)->toBe($this->f->landlord->id)
        ->and($event->created_at->format('Y-m-d H:i'))->toBe('2026-10-20 09:30')
        ->and($this->f->repair->fresh()->default_payer->value)->toBe('landlord');
});

it('plačnika ne moreta spremeniti najemnik ali izvajalec', function () {
    $this->actingAs($this->f->reporter)->postJson("{$this->url}/payer", ['payer' => 'tenant'])->assertForbidden();
    $this->actingAs($this->f->contractor)->postJson("{$this->url}/payer", ['payer' => 'tenant'])->assertForbidden();
});

it('izvajalca je mogoče dodeliti samo potrjeni prijavi; izvajalec prejme e-pošto s povezavo', function () {
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/assign", ['contractor_id' => $this->f->contractor->id])->assertConflict();

    $this->actingAs($this->f->landlord)->postJson("{$this->url}/confirm");
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/assign", ['contractor_id' => $this->f->contractor->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.contractor.name', 'Vodoinštalacije Breznik');

    Notification::assertSentTo($this->f->contractor, RepairAssigned::class, fn (RepairAssigned $n) => str_ends_with(
        $n->toMail($this->f->contractor)->actionUrl, '/okvare/'.$this->f->repair->id
    ));
});

it('potrdi in dodeli izvajalcu v enem koraku (WF-03)', function () {
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/confirm", [
        'payer' => 'tenant', 'contractor_id' => $this->f->contractor->id,
    ])->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.payer', 'tenant');

    expect(RepairEvent::where('kind', 'status')->orderBy('id')->pluck('to_status')->all())
        ->toBe(['reported', 'confirmed', 'assigned']);
});

it('izbira vsebuje samo izvajalce, povabljene v stavbo', function () {
    $outsider = memberOf(\App\Models\Building::factory()->create(), Role::Contractor);
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/confirm");

    $this->actingAs($this->f->landlord)->postJson("{$this->url}/assign", ['contractor_id' => $outsider->id])
        ->assertUnprocessable()->assertJsonValidationErrors('contractor_id');
    $this->actingAs($this->f->landlord)->getJson("/api/buildings/{$this->f->building->id}/contractors")
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Vodoinštalacije Breznik');
    expect($this->f->repair->fresh()->status)->toBe(RepairStatus::Confirmed);
});

it('prijavitelj prejme e-pošto ob vsaki spremembi stanja', function () {
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/confirm", ['contractor_id' => $this->f->contractor->id]);

    Notification::assertSentToTimes($this->f->reporter, RepairStatusChanged::class, 2);
    Notification::assertSentTo($this->f->reporter, RepairStatusChanged::class,
        fn (RepairStatusChanged $n) => $n->toMail($this->f->reporter)->subject === 'Prijava '.$this->f->repair->number().': Dodeljeno');
});
