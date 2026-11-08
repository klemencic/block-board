<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = ['building_id', 'author_id', 'title', 'body', 'floor', 'published_at'];

    protected function casts(): array
    {
        return ['floor' => 'integer', 'published_at' => 'datetime'];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Obveščenost stanovanj (US-15): ciljna stanovanja so stanovanja s prejemniki ob objavi
     * (samo nadstropje obvestila, brez praznih stanovanj). Stanovanje je obveščeno,
     * ko branje potrdi vsaj en njegov najemnik.
     *
     * @return array{informed: \Illuminate\Support\Collection<int, int>, uninformed: \Illuminate\Support\Collection<int, int>}
     */
    public function informedApartments(): array
    {
        $readers = $this->reads()->pluck('user_id')->all();
        $byApartment = $this->recipients()->get()->groupBy('apartment_id');

        $informed = $byApartment->filter(fn ($recipients) => $recipients->whereIn('user_id', $readers)->isNotEmpty())->keys();

        return [
            'informed' => $informed->values(),
            'uninformed' => $byApartment->keys()->diff($informed)->values(),
        ];
    }

    public function targetLabel(): string
    {
        return $this->floor === null ? 'Cela stavba' : ($this->floor === 0 ? 'Pritličje' : $this->floor.'. nadstropje');
    }

    /** Nadstropja, v katerih ima uporabnik trenutni najem v stavbi. */
    public static function floorsOf(User $user, int $buildingId): array
    {
        return LeaseTenant::where('user_id', $user->id)
            ->whereNull('moved_out_on')
            ->whereHas('lease', fn ($q) => $q->whereIn('status', LeaseStatus::occupying())
                ->whereHas('apartment', fn ($q) => $q->where('building_id', $buildingId)))
            ->with('lease.apartment')
            ->get()
            ->map(fn ($t) => $t->lease->apartment->floor)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Obvestila, ki jih najemnik sme videti: za celo stavbo ali za nadstropje njegovega stanovanja (US-14).
     */
    public function scopeVisibleToTenant(Builder $query, User $user, int $buildingId): void
    {
        $floors = self::floorsOf($user, $buildingId);

        $query->where('building_id', $buildingId)
            ->where(fn ($q) => $q->whereNull('floor')->orWhereIn('floor', $floors));
    }
}
