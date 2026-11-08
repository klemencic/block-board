<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * E-pošta z naslovom obvestila in povezavo nanj (US-14, #134).
 * Uspešna dostava in napake se zapišejo pri prejemniku, zato napake niso skrite.
 */
class AnnouncementPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly Announcement $announcement, public readonly ?int $recipientId = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Obvestilo: '.$this->announcement->title)
            ->line('V stavbi '.$this->announcement->building->name.' je objavljeno novo obvestilo:')
            ->line('»'.$this->announcement->title.'«')
            ->action('Preberi obvestilo', rtrim(config('app.frontend_url'), '/').'/obvestila/'.$this->announcement->id);
    }

    /** Zapis uspešne dostave (klic iz poslušalca NotificationSent). */
    public function markSent(object $notifiable): void
    {
        AnnouncementRecipient::where('announcement_id', $this->announcement->id)
            ->where('user_id', $notifiable->getKey())
            ->update(['mail_sent_at' => now(), 'mail_failed_at' => null, 'mail_error' => null]);
    }

    /** Ko pošiljanje dokončno ne uspe, se napaka zapiše pri prejemniku. */
    public function failed(Throwable $e): void
    {
        AnnouncementRecipient::where('announcement_id', $this->announcement->id)
            ->when($this->recipientId, fn ($q) => $q->where('user_id', $this->recipientId))
            ->whereNull('mail_sent_at')
            ->update(['mail_failed_at' => now(), 'mail_error' => Str::limit($e->getMessage(), 250)]);
    }
}
