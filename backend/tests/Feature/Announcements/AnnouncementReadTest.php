<?php

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Membership;
use App\Services\AnnouncementService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->building = Building::factory()->create();
    $this->landlord = memberOf($this->building, Role::Landlord);

    $lease = function (string $label, int $floor, array $shares) {
        $lease = Lease::factory()->for(Apartment::factory()->for($this->building)->state(['label' => $label, 'floor' => $floor]))
            ->withTenants($shares)->create(['status' => LeaseStatus::Active]);
        foreach ($lease->tenants as $i => $t) {
            Membership::create(['user_id' => $t->user_id, 'building_id' => $this->building->id, 'role' => Role::Tenant]);
            $t->user->update(['phone' => "040 000 {$label}{$i}"]);
        }

        return $lease->tenants->map->user;
    };

    // Pritličje: 1A in 1B zasedena, 1C prazno. 1. nadstropje: 2A (dva sostanovalca), 2B.
    [$this->ana] = $lease('1A', 0, [50000]);
    [$this->marko] = $lease('1B', 0, [40000]);
    Apartment::factory()->for($this->building)->create(['label' => '1C', 'floor' => 0]);
    [$this->luka, $this->eva] = $lease('2A', 1, [30000, 30000]);
    [$this->tina] = $lease('2B', 1, [45000]);

    $this->service = app(AnnouncementService::class);
    $this->all = $this->service->publish($this->building, $this->landlord, ['title' => 'Zapora vode', 'body' => 'x']);
    $this->floor1 = $this->service->publish($this->building, $this->landlord, ['title' => 'Pleskanje', 'body' => 'y', 'floor' => 1]);
});

function readAs($user, Announcement $a): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($user)->postJson("/api/announcements/{$a->id}/read");
}

it('ob prvi potrditvi zabeleži uporabnika in čas, ponovni klik ne ustvari novega zapisa', function () {
    $this->travelTo('2026-10-21 18:30');
    readAs($this->ana, $this->all)->assertOk()->assertJsonPath('data.read_at', fn ($v) => str_starts_with($v, '2026-10-21'));

    $this->travelTo('2026-10-22 08:00');
    readAs($this->ana, $this->all)->assertOk();

    $read = AnnouncementRead::sole();
    expect($read->user_id)->toBe($this->ana->id)
        ->and($read->announcement_id)->toBe($this->all->id)
        ->and($read->read_at->format('Y-m-d H:i'))->toBe('2026-10-21 18:30');
});

it('najemnik lahko potrdi samo obvestila, ki jih sme videti', function () {
    readAs($this->ana, $this->floor1)->assertForbidden();
    readAs($this->landlord, $this->all)->assertForbidden();
    expect(AnnouncementRead::count())->toBe(0);
});

it('lastnik vidi razmerje obveščenih stanovanj; stanovanje šteje enkrat, ko potrdi vsaj en najemnik', function () {
    readAs($this->luka, $this->all);
    readAs($this->eva, $this->all);   // isto stanovanje 2A
    readAs($this->ana, $this->all);

    $list = collect($this->actingAs($this->landlord)->getJson("/api/buildings/{$this->building->id}/announcements")->json('data'))->keyBy('title');

    // Cela stavba: 4 zasedena stanovanja (1C je prazno), obveščena 1A in 2A.
    expect($list['Zapora vode']['informed'])->toBe(['informed' => 2, 'total' => 4]);
});

it('pri obvestilu za nadstropje se štejejo samo stanovanja tega nadstropja', function () {
    readAs($this->tina, $this->floor1);

    $this->actingAs($this->landlord)->getJson("/api/announcements/{$this->floor1->id}")
        ->assertJsonPath('data.informed', ['informed' => 1, 'total' => 2]);
});

it('klik na razmerje prikaže stanovanja brez potrditve z imeni in telefoni najemnikov', function () {
    readAs($this->ana, $this->all);

    $rows = $this->actingAs($this->landlord)->getJson("/api/announcements/{$this->all->id}/uninformed")->assertOk()->json('data');

    expect(collect($rows)->pluck('label')->all())->toBe(['1B', '2A', '2B'])
        ->and($rows[1]['tenants'])->toBe([
            ['name' => $this->luka->name, 'phone' => $this->luka->phone],
            ['name' => $this->eva->name, 'phone' => $this->eva->phone],
        ]);
});

it('seznam brez potrditve je omejen na nadstropje in brez praznih stanovanj', function () {
    $rows = $this->actingAs($this->landlord)->getJson("/api/announcements/{$this->floor1->id}/uninformed")->json('data');
    expect(collect($rows)->pluck('label')->all())->toBe(['2A', '2B']);

    $all = $this->actingAs($this->landlord)->getJson("/api/announcements/{$this->all->id}/uninformed")->json('data');
    expect(collect($all)->pluck('label')->all())->not->toContain('1C');
});

it('seznam brez potrditve vidi samo lastnik stavbe', function () {
    $this->actingAs($this->ana)->getJson("/api/announcements/{$this->all->id}/uninformed")->assertForbidden();
});

it('najemnik vidi, katera obvestila je že potrdil, in število neprebranih v meniju', function () {
    readAs($this->luka, $this->all);

    $list = collect($this->actingAs($this->luka)->getJson("/api/buildings/{$this->building->id}/announcements")->json('data'))->keyBy('title');
    expect($list['Zapora vode']['read_at'])->not->toBeNull()
        ->and($list['Pleskanje']['read_at'])->toBeNull()
        ->and($list['Zapora vode'])->not->toHaveKey('informed');

    $this->actingAs($this->luka)->getJson("/api/buildings/{$this->building->id}/badges")->assertJsonPath('data./obvestila', 1);
});
