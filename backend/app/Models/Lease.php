<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lease extends Model
{
    use HasFactory;

    protected $fillable = [
        'apartment_id', 'starts_on', 'ends_on', 'notice_period_days', 'monthly_rent_cents',
        'deposit_cents', 'status', 'notice_given_on', 'terminates_on', 'deposit_returned_cents',
    ];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'notice_given_on' => 'date',
            'terminates_on' => 'date',
            'status' => LeaseStatus::class,
            'notice_period_days' => 'integer',
            'monthly_rent_cents' => 'integer',
            'deposit_cents' => 'integer',
            'deposit_returned_cents' => 'integer',
        ];
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(LeaseTenant::class)->orderBy('id');
    }

    /** Najemi v stanju aktiven ali v odpovednem roku. */
    public function scopeOccupying(Builder $query): void
    {
        $query->whereIn('status', LeaseStatus::occupying());
    }

    public function hasTenant(User $user): bool
    {
        return $this->tenants()->where('user_id', $user->id)->exists();
    }

    public function buildingId(): int
    {
        return $this->apartment->building_id;
    }
}
