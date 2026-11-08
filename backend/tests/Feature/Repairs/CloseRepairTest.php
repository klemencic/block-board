<?php

use App\Enums\ChargeType;
use App\Enums\Payer;
use App\Enums\RepairStatus;
use App\Models\Attachment;
use App\Models\Charge;
use App\Notifications\RepairReturned;
use App\Services\RepairWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo('2026-10-20 08:00');
    $this->workflow = app(RepairWorkflow::class);
});

/** Prijava v stanju »opravljeno« s stroškom 85,00 €. */
function doneRepair(string $payer = 'landlord'): object
{
    $f = repairFixture($payer);
    $w = app(RepairWorkflow::class);
    $w->confirm($f->repair, $f->landlord);
    $w->assign($f->repair, $f->landlord, $f->contractor);
    $w->schedule($f->repair, $f->contractor, now()->addDays(2));
    $w->complete($f->repair, $f->contractor, 8500, [UploadedFile::fake()->create('po.jpg', 100, 'image/jpeg')]);
    $f->url = '/api/repair-requests/'.$f->repair->id;

    return $f;
}

it('pri opravljeni prijavi lastnik vidi strošek in fotografije izvajalca ter jo zaključi', function () {
    $f = doneRepair();

    $this->actingAs($f->landlord)->getJson($f->url)
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.cost_cents', 8500)
        ->assertJsonPath('data.photos.0.context', 'completion');

    $this->actingAs($f->landlord)->postJson("{$f->url}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.cost_cents', 8500);
});

it('vrnitev v popravek zahteva obrazložitev, prijava preide v dogovorjen termin, izvajalec prejme e-pošto', function () {
    $f = doneRepair();

    $this->actingAs($f->landlord)->postJson("{$f->url}/return", [])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason' => 'Pri vrnitvi v popravek je obrazložitev obvezna.']);

    $this->actingAs($f->landlord)->postJson("{$f->url}/return", ['reason' => 'Bojler še vedno ne greje do 60 °C.'])
        ->assertOk()->assertJsonPath('data.status', 'scheduled');

    Notification::assertSentTo($f->contractor, RepairReturned::class,
        fn (RepairReturned $n) => in_array('Obrazložitev: Bojler še vedno ne greje do 60 °C.', $n->toMail($f->contractor)->introLines, true));
});

it('po vrnitvi izvajalec ponovno odda poročilo, ki se ponovno preveri', function () {
    $f = doneRepair();
    $this->actingAs($f->landlord)->postJson("{$f->url}/return", ['reason' => 'Dopolnite poročilo.']);

    $this->actingAs($f->contractor)->post("{$f->url}/complete", ['cost_cents' => 9500], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('photos');

    $this->actingAs($f->contractor)->post("{$f->url}/complete", [
        'cost_cents' => 9500, 'photos' => [UploadedFile::fake()->create('popravljeno.png', 100, 'image/png')],
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.status', 'done')->assertJsonPath('data.cost_cents', 9500);

    // Prejšnje fotografije izvedbe so zamenjane.
    expect(Attachment::where('context', 'completion')->pluck('original_name')->all())->toBe(['popravljeno.png']);
});

it('vrnitev in zaključek sta mogoča samo pri opravljeni prijavi', function () {
    $f = repairFixture();
    $url = '/api/repair-requests/'.$f->repair->id;

    $this->actingAs($f->landlord)->postJson("$url/close")->assertConflict();
    $this->actingAs($f->landlord)->postJson("$url/return", ['reason' => 'x'])->assertConflict();
});

it('če je plačnik najemnik, se prijavitelju privzeto doda obveznost popravila za naslednji mesec', function () {
    $f = doneRepair('tenant');

    $candidates = $this->actingAs($f->landlord)->getJson("{$f->url}/payer-candidates")->json('data');
    expect(collect($candidates)->firstWhere('default', true)['name'])->toBe($f->reporter->name);

    $this->actingAs($f->landlord)->postJson("{$f->url}/close")->assertOk();

    $charge = Charge::sole();
    expect($charge->type)->toBe(ChargeType::Repair)
        ->and($charge->amount_cents)->toBe(8500)
        ->and($charge->leaseTenant->user_id)->toBe($f->reporter->id)
        ->and($charge->period->toDateString())->toBe('2026-11-01')
        ->and($charge->repair_request_id)->toBe($f->repair->id);
});

it('lastnik lahko izbere drugega najemnika stanovanja, ne pa najemnika drugega stanovanja', function () {
    $f = doneRepair('tenant');
    $roommate = $f->lease->tenants[1];
    $foreign = \App\Models\Lease::factory()->withTenants()->create(['status' => 'active'])->tenants[0];

    $this->actingAs($f->landlord)->postJson("{$f->url}/close", ['lease_tenant_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('lease_tenant_id');

    $this->actingAs($f->landlord)->postJson("{$f->url}/close", ['lease_tenant_id' => $roommate->id])->assertOk();
    expect(Charge::sole()->lease_tenant_id)->toBe($roommate->id);
});

it('če je plačnik lastnik, obveznost ne nastane, strošek ostane viden', function () {
    $f = doneRepair('landlord');

    $this->actingAs($f->landlord)->postJson("{$f->url}/close")->assertOk();

    expect(Charge::count())->toBe(0);
    $this->actingAs($f->reporter)->getJson($f->url)->assertJsonPath('data.cost_cents', 8500);
});

it('zaključene prijave ni več mogoče spreminjati; časovnica vsebuje vse korake z avtorjem in časom', function () {
    $f = doneRepair();
    $this->actingAs($f->landlord)->postJson("{$f->url}/close");

    $this->actingAs($f->landlord)->postJson("{$f->url}/return", ['reason' => 'x'])->assertConflict();
    $this->actingAs($f->landlord)->postJson("{$f->url}/payer", ['payer' => 'tenant'])->assertConflict();
    $this->actingAs($f->contractor)->post("{$f->url}/complete", ['cost_cents' => 1, 'photos' => [UploadedFile::fake()->create('x.jpg', 1, 'image/jpeg')]], ['Accept' => 'application/json'])->assertConflict();
    expect($f->repair->fresh()->payer)->toBe(Payer::Landlord);

    $timeline = collect($this->actingAs($f->reporter)->getJson($f->url)->json('data.timeline'))->where('kind', 'status');
    expect($timeline->pluck('to_status')->values()->all())->toBe(['reported', 'confirmed', 'assigned', 'scheduled', 'done', 'closed'])
        ->and($timeline->every(fn ($e) => $e['user'] && $e['created_at']))->toBeTrue();
});

it('najemnik ob obveznosti popravila vidi prijavo kot izvor', function () {
    $f = doneRepair('tenant');
    $this->actingAs($f->landlord)->postJson("{$f->url}/close");

    $this->actingAs($f->reporter)->getJson('/api/charges/'.Charge::sole()->id.'/source')
        ->assertOk()
        ->assertJsonPath('data.type', 'repair')
        ->assertJsonPath('data.data.number', $f->repair->number())
        ->assertJsonPath('data.data.cost_cents', 8500);
});

it('samo lastnik lahko zaključi ali vrne prijavo', function () {
    $f = doneRepair();

    $this->actingAs($f->contractor)->postJson("{$f->url}/close")->assertForbidden();
    $this->actingAs($f->reporter)->postJson("{$f->url}/return", ['reason' => 'x'])->assertForbidden();
    expect($f->repair->fresh()->status)->toBe(RepairStatus::Done);
});
