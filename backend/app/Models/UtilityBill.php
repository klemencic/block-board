<?php

namespace App\Models;

use App\Enums\SplitMethod;
use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UtilityBill extends Model
{
    protected $fillable = [
        'building_id', 'apartment_id', 'type', 'amount_cents', 'period_start', 'period_end', 'split_method', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => UtilityType::class,
            'split_method' => SplitMethod::class,
            'amount_cents' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
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

    public function shares(): HasMany
    {
        return $this->hasMany(BillShare::class);
    }

    public function isForBuilding(): bool
    {
        return $this->apartment_id === null;
    }
}
