<?php

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Membership;
use App\Notifications\AnnouncementPublished;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->building = Building::factory()->create(['name' => 'Stavba Lipa']);
    $this->landlord = memberOf($this->building, Role::Landlord);

    // Pritličje: 1A (en najemnik), 1C prazno; 1. nadstropje: 2C (dva sostanovalca).
    $lease = fn (string $label, int $floor, array $shares) => tap(
        Lease::factory()->for(Apartment::factory()->for($this->building)->state(['label' => $label, 'floor' => $floor]))
            ->withTenants($shares)->create(['status' => LeaseStatus::Active]),
        fn ($l) => $l->tenants->each(fn ($t) => Membership::create(['user_id' => $t->user_id, 'building_id' => $this->building->id, 'role' => Role::Tenant]))
    );
    $this->ground = $lease('1A', 0, [50000])->tenants[0]->user;
    Apartment::factory()->for($this->building)->create(['label' => '1C', 'floor' => 0]);
    $first = $lease('2C', 1, [30000, 30000]);
    [$this->jan, $this->nik] = [$first->tenants[0]->user, $first->tenants[1]->user];
});

function publish(array $data): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->landlord)->postJson('/api/buildings/'.test()->building->id.'/announcements', $data);
}

it('obvezna sta naslov in besedilo; nadstropje mora pripadati stavbi', function () {
    publish([])->assertUnprocessable()->assertJsonValidationErrors(['title', 'body']);
    publish(['title' => 'x', 'body' => 'y', 'floor' => 7])
        ->assertUnprocessable()->assertJsonValidationErrors(['floor' => 'Izbrano nadstropje ne pripada stavbi.']);
});

it('shrani obvestilo z avtorjem in časom objave', function () {
    $this->travelTo('2026-10-21 09:15');

    publish(['title' => 'Zapora vode', 'body' => 'V četrtek od 8. do 12. ure ne bo vode.'])
        ->assertCreated()
        ->assertJsonPath('data.target_label', 'Cela stavba')
        ->assertJsonPath('data.author', $this->landlord->name);

    $announcement = Announcement::sole();
    expect($announcement->author_id)->toBe($this->landlord->id)
        ->and($announcement->published_at->format('Y-m-d H:i'))->toBe('2026-10-21 09:15');
});

it('ob objavi pošlje e-pošto z naslovom in povezavo samo ciljnim najemnikom', function () {
    Notification::fake();

    publish(['title' => 'Dela v 1. nadstropju', 'body' => 'Menjava cevi.', 'floor' => 1])->assertCreated();
    $announcement = Announcement::sole();

    Notification::assertSentTo([$this->jan, $this->nik], AnnouncementPublished::class, function (AnnouncementPublished $n) use ($announcement) {
        $mail = $n->toMail($this->jan);

        return str_contains($mail->subject, 'Dela v 1. nadstropju')
            && str_ends_with($mail->actionUrl, '/obvestila/'.$announcement->id);
    });
    Notification::assertNotSentTo([$this->ground, $this->landlord], AnnouncementPublished::class);

    // Prazna stanovanja niso prejemniki.
    expect(AnnouncementRecipient::pluck('apartment_id')->unique()->count())->toBe(1);
});

it('dostava je sledljiva: uspešna e-pošta in napaka se zapišeta pri prejemniku', function () {
    publish(['title' => 'Hišni red', 'body' => 'Nov hišni red velja od 1. 11.']);
    $announcement = Announcement::sole();

    expect(AnnouncementRecipient::whereNotNull('mail_sent_at')->count())->toBe(3);

    // Simulirana dokončna napaka pošiljanja za enega prejemnika.
    AnnouncementRecipient::query()->update(['mail_sent_at' => null]);
    (new AnnouncementPublished($announcement, $this->jan->id))->failed(new RuntimeException('SMTP ni dosegljiv'));

    $failed = AnnouncementRecipient::where('user_id', $this->jan->id)->sole();
    expect($failed->mail_failed_at)->not->toBeNull()
        ->and($failed->mail_error)->toBe('SMTP ni dosegljiv');
    $this->actingAs($this->landlord)->getJson("/api/announcements/{$announcement->id}")
        ->assertJsonPath('data.delivery.failed', 1);
});

it('obvestilo za nadstropje vidijo samo najemniki tega nadstropja', function () {
    publish(['title' => 'Za 1. nadstropje', 'body' => 'x', 'floor' => 1]);
    $announcement = Announcement::sole();

    $this->actingAs($this->jan)->getJson("/api/announcements/{$announcement->id}")->assertOk();
    $this->actingAs($this->ground)->getJson("/api/announcements/{$announcement->id}")->assertForbidden();
    $this->actingAs($this->ground)->getJson("/api/buildings/{$this->building->id}/announcements")->assertOk()->assertJsonCount(0, 'data');
});

it('najemnik vidi obvestila od najnovejšega z datumom in časom, ne pa obvestil drugih stavb', function () {
    $this->travelTo('2026-10-20 08:00');
    publish(['title' => 'Prvo', 'body' => 'a']);
    $this->travelTo('2026-10-21 08:00');
    publish(['title' => 'Drugo', 'body' => 'b']);

    $other = Building::factory()->create();
    Announcement::create(['building_id' => $other->id, 'author_id' => $this->landlord->id, 'title' => 'Tuje', 'body' => 'c', 'published_at' => now()]);

    $list = $this->actingAs($this->jan)->getJson("/api/buildings/{$this->building->id}/announcements")->assertOk()->json('data');
    expect(collect($list)->pluck('title')->all())->toBe(['Drugo', 'Prvo'])
        ->and($list[0]['published_at'])->not->toBeNull()
        ->and($list[0]['body'])->toBe('b')
        ->and($list[0])->not->toHaveKey('delivery');
});

it('seznam deluje tudi brez obvestil', function () {
    $this->actingAs($this->jan)->getJson("/api/buildings/{$this->building->id}/announcements")->assertOk()->assertExactJson(['data' => []]);
});

it('obvestila lahko objavi samo lastnik', function () {
    $this->actingAs($this->jan)->postJson("/api/buildings/{$this->building->id}/announcements", ['title' => 'x', 'body' => 'y'])->assertForbidden();
});
