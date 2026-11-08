<?php

namespace App\Notifications;

use App\Models\RepairRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lastnik prejme e-pošto s terminom izvedbe (US-11, #125). Prijavitelj ga prejme v RepairStatusChanged.
 */
class RepairScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RepairRequest $repair)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $repair = $this->repair->loadMissing('contractor');

        return (new MailMessage)
            ->subject("Termin popravila {$repair->number()}: ".$repair->scheduled_at->format('j. n. Y \o\b H:i'))
            ->line("{$repair->contractor->name} bo okvaro »{$repair->title}« odpravil ".$repair->scheduled_at->format('j. n. Y \o\b H:i').'.')
            ->action('Odpri prijavo', rtrim(config('app.frontend_url'), '/').'/okvare/'.$repair->id);
    }
}
