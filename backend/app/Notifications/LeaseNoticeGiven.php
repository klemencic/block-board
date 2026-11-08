<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lastnik in vsi sostanovalci izvejo datum odpovedi in datum prenehanja (US-02, #99).
 */
class LeaseNoticeGiven extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lease $lease)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $apartment = $this->lease->apartment;

        return (new MailMessage)
            ->subject("Odpoved najema – stanovanje {$apartment->label}")
            ->line("Najem stanovanja {$apartment->label} v stavbi {$apartment->building->name} je odpovedan.")
            ->line('Datum odpovedi: '.$this->lease->notice_given_on->format('j. n. Y'))
            ->line('Datum prenehanja: '.$this->lease->terminates_on->format('j. n. Y'))
            ->action('Odpri najem', rtrim(config('app.frontend_url'), '/').'/');
    }
}
