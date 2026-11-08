<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedSpace extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_id', 'name', 'price_per_hour_cents', 'open_from', 'open_to',
        'max_hours_per_week', 'free_cancel_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_hour_cents' => 'integer',
            'max_hours_per_week' => 'integer',
            'free_cancel_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
