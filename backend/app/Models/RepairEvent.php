<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Korak v obravnavi okvare: prehod stanja, sprememba plačnika ali opomba (ADR-0006).
 */
class RepairEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['repair_request_id', 'user_id', 'kind', 'from_status', 'to_status', 'note', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repairRequest(): BelongsTo
    {
        return $this->belongsTo(RepairRequest::class);
    }
}
