<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseTenant extends Model
{
    protected $fillable = ['lease_id', 'user_id', 'rent_share_cents', 'moved_in_on', 'moved_out_on'];

    protected function casts(): array
    {
        return [
            'rent_share_cents' => 'integer',
            'moved_in_on' => 'date',
            'moved_out_on' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
