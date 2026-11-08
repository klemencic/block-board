<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use App\Enums\Payer;
use App\Enums\RepairStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RepairRequest extends Model
{
    protected $fillable = [
        'building_id', 'apartment_id', 'shared_space_id', 'reported_by', 'category_id', 'title', 'description',
        'status', 'default_payer', 'payer', 'contractor_id', 'scheduled_at', 'cost_cents', 'payer_lease_tenant_id', 'closed_at',
    ];

    protected $attributes = ['status' => 'reported'];

    protected function casts(): array
    {
        return [
            'status' => RepairStatus::class,
            'payer' => Payer::class,
            'default_payer' => Payer::class,
            'scheduled_at' => 'datetime',
            'closed_at' => 'datetime',
            'cost_cents' => 'integer',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function sharedSpace(): BelongsTo
    {
        return $this->belongsTo(SharedSpace::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RepairCategory::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }

    public function payerTenant(): BelongsTo
    {
        return $this->belongsTo(LeaseTenant::class, 'payer_lease_tenant_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RepairEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('id');
    }

    public function number(): string
    {
        return 'R-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function locationLabel(): string
    {
        return $this->apartment ? 'Stanovanje '.$this->apartment->label : ($this->sharedSpace?->name ?? 'Skupni prostor');
    }

    /**
     * Prijave, ki jih uporabnik sme videti (US-09, US-11):
     * lastnik vse v stavbi, izvajalec dodeljene, najemnik svoje in prijave svojega stanovanja.
     */
    public function scopeVisibleTo(Builder $query, User $user, int $buildingId): void
    {
        $query->where('building_id', $buildingId);

        if ($user->isLandlordOf($buildingId)) {
            return;
        }

        $apartmentIds = LeaseTenant::where('user_id', $user->id)
            ->whereNull('moved_out_on')
            ->whereHas('lease', fn ($q) => $q->whereIn('status', LeaseStatus::occupying()))
            ->with('lease')
            ->get()
            ->pluck('lease.apartment_id');

        $query->where(fn ($q) => $q
            ->where('contractor_id', $user->id)
            ->orWhere('reported_by', $user->id)
            ->orWhereIn('apartment_id', $apartmentIds));
    }
}
