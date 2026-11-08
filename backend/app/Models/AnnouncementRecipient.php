<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = ['announcement_id', 'apartment_id', 'user_id', 'mail_sent_at', 'mail_failed_at', 'mail_error'];

    protected function casts(): array
    {
        return ['mail_sent_at' => 'datetime', 'mail_failed_at' => 'datetime'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
