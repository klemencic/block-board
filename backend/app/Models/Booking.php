<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = ['shared_space_id', 'lease_tenant_id', 'starts_at', 'ends_at', 'fee_cents', 'status', 'cancelled_at'];

    protected $attributes = ['status' => 'confirmed'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fee_cents' => 'integer',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(SharedSpace::class, 'shared_space_id');
    }

    public function leaseTenant(): BelongsTo
    {
        return $this->belongsTo(LeaseTenant::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', 'cancelled');
    }

    public function minutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }
}
