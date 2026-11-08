<?php

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Building;
use App\Models\LeaseTenant;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Objava obvestil (US-14, F9).
 */
class AnnouncementService
{
    /**
     * Objavi obvestilo za celo stavbo ali eno nadstropje in ga pošlje ciljnim najemnikom.
     * Prejemniki se zabeležijo ob objavi, skupaj s stanjem dostave e-pošte.
     *
     * @param  array{title: string, body: string, floor?: ?int}  $data
     */
    public function publish(Building $building, User $author, array $data): Announcement
    {
        $announcement = DB::transaction(function () use ($building, $author, $data) {
            $announcement = Announcement::create([
                'building_id' => $building->id,
                'author_id' => $author->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'floor' => $data['floor'] ?? null,
                'published_at' => now(),
            ]);

            foreach ($this->targetTenants($building, $announcement->floor) as $tenant) {
                AnnouncementRecipient::firstOrCreate([
                    'announcement_id' => $announcement->id,
                    'apartment_id' => $tenant->lease->apartment_id,
                    'user_id' => $tenant->user_id,
                ]);
            }

            return $announcement;
        });

        // Vsak najemnik prejme eno e-pošto, tudi če ima več stanovanj v nadstropju.
        $announcement->recipients()->with('user')->get()->unique('user_id')
            ->each(fn (AnnouncementRecipient $r) => $r->user->notify(new AnnouncementPublished($announcement, $r->user_id)));

        return $announcement;
    }

    /**
     * Najemniki ciljnih stanovanj: trenutni sostanovalci aktivnih najemov in najemov v odpovednem roku.
     * Prazna stanovanja nimajo prejemnikov.
     *
     * @return Collection<int, LeaseTenant>
     */
    public function targetTenants(Building $building, ?int $floor): Collection
    {
        return LeaseTenant::query()
            ->whereNull('moved_out_on')
            ->whereHas('lease', fn ($q) => $q->whereIn('status', LeaseStatus::occupying())
                ->whereHas('apartment', fn ($q) => $q->where('building_id', $building->id)
                    ->when($floor !== null, fn ($q) => $q->where('floor', $floor))))
            ->with('lease', 'user')
            ->orderBy('id')
            ->get();
    }
}
