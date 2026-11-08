<?php

namespace App\Models;

use App\Enums\ChargeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Obveznost: vsak znesek, ki ga najemnik dolguje lastniku.
 * Izdane obveznosti se ne spreminjajo in ne brišejo; popravek je nov zapis.
 */
class Charge extends Model
{
    /** Polja, ki jih po izdaji ni mogoče spremeniti. */
    public const IMMUTABLE = ['lease_tenant_id', 'type', 'description', 'amount_cents', 'period', 'due_date'];

    protected $fillable = [
        'lease_tenant_id', 'type', 'description', 'amount_cents', 'period', 'due_date', 'paid_at',
        'bill_share_id', 'booking_id', 'repair_request_id',
    ];

    protected function casts(): array
    {
        return [
            'bill_share_id' => 'integer',
            'booking_id' => 'integer',
            'repair_request_id' => 'integer',
            'type' => ChargeType::class,
            'amount_cents' => 'integer',
            'period' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Charge $charge) {
            if ($charge->isDirty(self::IMMUTABLE)) {
                throw new LogicException('Izdane obveznosti ni mogoče spreminjati. Popravek vnesite kot novo obveznost.');
            }
        });

        static::deleting(function () {
            throw new LogicException('Izdane obveznosti ni mogoče izbrisati.');
        });
    }

    public function leaseTenant(): BelongsTo
    {
        return $this->belongsTo(LeaseTenant::class);
    }

    /** Obveznost, ki je nastala iz položnice, rezervacije ali popravila (US-04, #105). */
    public function hasSource(): bool
    {
        return in_array($this->type, [ChargeType::Utility, ChargeType::Booking, ChargeType::Repair], true);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date->lt(today());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /** Stanje: open, paid ali overdue (US-04). */
    public function status(): string
    {
        return match (true) {
            $this->isPaid() => 'paid',
            $this->isOverdue() => 'overdue',
            default => 'open',
        };
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('paid_at')->whereDate('due_date', '<', today());
    }

    public function scopeInBuilding(Builder $query, int $buildingId): void
    {
        $query->whereHas('leaseTenant.lease.apartment', fn ($q) => $q->where('building_id', $buildingId));
    }
}
