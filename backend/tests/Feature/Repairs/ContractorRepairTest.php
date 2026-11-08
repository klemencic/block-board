<?php

use App\Enums\RepairStatus;
use App\Enums\Role;
use App\Models\Attachment;
use App\Models\RepairRequest;
use App\Notifications\RepairCompleted;
use App\Notifications\RepairScheduled;
use App\Notifications\RepairStatusChanged;
use App\Services\RepairWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo('2026-10-20 08:00');
    $this->f = repairFixture();
    $workflow = app(RepairWorkflow::class);
    $workflow->confirm($this->f->repair, $this->f->landlord);
    $workflow->assign($this->f->repair, $this->f->landlord, $this->f->contractor);
    $this->url = '/api/repair-requests/'.$this->f->repair->id;
});

function photo(string $name = 'po_popravilu.jpg', int $kb = 300, string $mime = 'image/jpeg'): UploadedFile
{
    return UploadedFile::fake()->create($name, $kb, $mime);
}

it('izvajalec vidi samo dodeljene prijave z opisom, lokacijo in kontaktom prijavitelja', function () {
    // Druga prijava v isti stavbi, ki ni dodeljena temu izvajalcu.
    app(RepairWorkflow::class)->report($this->f->building, $this->f->reporter, [
        'title' => 'Druga okvara', 'description' => 'x', 'category_id' => $this->f->category->id, 'apartment_id' => $this->f->lease->apartment_id,
    ]);

    $list = $this->actingAs($this->f->contractor)->getJson("/api/buildings/{$this->f->building->id}/repair-requests")
        ->assertOk()->assertJsonCount(1, 'data')->json('data.0');

    expect($list)->toMatchArray(['title' => 'Bojler ne greje vode', 'description' => 'Od sinoči teče samo hladna voda.'])
        ->and($list['category']['name'])->toBe('Ogrevanje in topla voda')
        ->and($list['building']['address'])->toBe($this->f->building->address)
        ->and($list['location']['apartment']['label'])->toBe('2C')
        ->and($list['reporter'])->toBe(['id' => $this->f->reporter->id, 'name' => $this->f->reporter->name, 'phone' => '040 123 456']);

    $other = RepairRequest::where('title', 'Druga okvara')->sole();
    $this->actingAs($this->f->contractor)->getJson("/api/repair-requests/{$other->id}")->assertForbidden();
});

it('izvajalec nima dostopa do najemov, obveznosti in pregleda stavbe', function () {
    $this->actingAs($this->f->contractor)->getJson("/api/leases/{$this->f->lease->id}")->assertForbidden();
    $this->actingAs($this->f->contractor)->getJson("/api/buildings/{$this->f->building->id}/charges")->assertForbidden();
    $this->actingAs($this->f->contractor)->getJson("/api/buildings/{$this->f->building->id}/leases")->assertForbidden();
});

it('termin mora biti v prihodnosti; prijava preide v dogovorjen termin, najemnik in lastnik prejmeta e-pošto', function () {
    $this->actingAs($this->f->contractor)->postJson("{$this->url}/schedule", ['scheduled_at' => '2026-10-19 10:00'])
        ->assertUnprocessable()->assertJsonValidationErrors(['scheduled_at' => 'Termin izvedbe mora biti v prihodnosti.']);

    $this->actingAs($this->f->contractor)->postJson("{$this->url}/schedule", ['scheduled_at' => '2026-10-22 10:00'])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.scheduled_at', '2026-10-22T10:00')
        ->assertJsonPath('data.timeline.4.user', 'Vodoinštalacije Breznik')
        ->assertJsonPath('data.timeline.4.note', 'termin 22. 10. 2026 ob 10:00');

    Notification::assertSentTo($this->f->landlord, RepairScheduled::class);
    Notification::assertSentTo($this->f->reporter, RepairStatusChanged::class,
        fn (RepairStatusChanged $n) => $n->event->to_status === 'scheduled' && $n->event->note === 'termin 22. 10. 2026 ob 10:00');
});

it('termin je mogoče vnesti samo pri dodeljeni prijavi in samo dodeljeni izvajalec', function () {
    $other = memberOf($this->f->building, Role::Contractor);
    $this->actingAs($other)->postJson("{$this->url}/schedule", ['scheduled_at' => '2026-10-22 10:00'])->assertForbidden();
    $this->actingAs($this->f->landlord)->postJson("{$this->url}/schedule", ['scheduled_at' => '2026-10-22 10:00'])->assertForbidden();

    $this->actingAs($this->f->contractor)->postJson("{$this->url}/schedule", ['scheduled_at' => '2026-10-22 10:00'])->assertOk();
    $this->actingAs($this->f->contractor)->postJson("{$this->url}/schedule", ['scheduled_at' => '2026-10-23 10:00'])->assertConflict();
});

it('kot opravljen je mogoče označiti samo nalog z dogovorjenim terminom', function () {
    $this->actingAs($this->f->contractor)->post("{$this->url}/complete", ['cost_cents' => 8500, 'photos' => [photo()]], ['Accept' => 'application/json'])
        ->assertConflict();
});

it('strošek je obvezen in ne sme biti negativen, potrebna je vsaj ena fotografija JPG ali PNG do 10 MB', function () {
    $this->actingAs($this->f->contractor)->postJson("{$this->url}/schedule", ['scheduled_at' => '2026-10-22 10:00']);
    $complete = fn (array $data) => $this->actingAs($this->f->contractor)->post("{$this->url}/complete", $data, ['Accept' => 'application/json']);

    $complete(['photos' => [photo()]])->assertUnprocessable()->assertJsonValidationErrors('cost_cents');
    $complete(['cost_cents' => -100, 'photos' => [photo()]])
        ->assertUnprocessable()->assertJsonValidationErrors(['cost_cents' => 'Strošek ne sme biti negativen.']);
    $complete(['cost_cents' => 8500])
        ->assertUnprocessable()->assertJsonValidationErrors(['photos' => 'Priložite vsaj eno fotografijo opravljenega dela.']);
    $complete(['cost_cents' => 8500, 'photos' => [photo('velika.png', 10241, 'image/png')]])
        ->assertUnprocessable()->assertJsonValidationErrors(['photos.0' => 'Datoteka »velika.png« je večja od 10 MB.']);
    $complete(['cost_cents' => 8500, 'photos' => [photo('video.mp4', 100, 'video/mp4')]])
        ->assertUnprocessable()->assertJsonValidationErrors('photos.0');

    expect($this->f->repair->fresh()->status)->toBe(RepairStatus::Scheduled);
});

it('po oddaji poročila prijava preide v opravljeno, lastnik prejme e-pošto, strošek in fotografije so zaklenjeni', function () {
    $this->actingAs($this->f->contractor)->postJson("{$this->url}/schedule", ['scheduled_at' => '2026-10-22 10:00']);

    $this->actingAs($this->f->contractor)->post("{$this->url}/complete", ['cost_cents' => 8500, 'photos' => [photo()]], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.cost_cents', 8500)
        ->assertJsonPath('data.photos.0.context', 'completion');

    Notification::assertSentTo($this->f->landlord, RepairCompleted::class);

    // Ponovna oddaja ni mogoča, dokler lastnik ne vrne prijave v popravek.
    $this->actingAs($this->f->contractor)->post("{$this->url}/complete", ['cost_cents' => 1, 'photos' => [photo()]], ['Accept' => 'application/json'])
        ->assertConflict();
    expect($this->f->repair->fresh()->cost_cents)->toBe(8500)
        ->and(Attachment::where('context', 'completion')->count())->toBe(1);
});
