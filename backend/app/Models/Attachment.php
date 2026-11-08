<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Fotografija ali dokument, dostopen samo prek API-ja po preverjanju pravic.
 */
class Attachment extends Model
{
    protected $fillable = ['attachable_type', 'attachable_id', 'uploaded_by', 'context', 'path', 'original_name', 'mime', 'size'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
