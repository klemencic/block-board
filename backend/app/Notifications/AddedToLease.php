<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Obstoječi uporabnik dobi dostop do novega najema brez novega računa.
 */
class AddedToLease extends Notification implements ShouldQueue
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
            ->subject('Dodani ste na najem – '.$apartment->building->name)
            ->line("Lastnik vas je dodal na najem stanovanja {$apartment->label} v stavbi {$apartment->building->name}.")
            ->action('Odpri BlockBoard', rtrim(config('app.frontend_url'), '/').'/moj-najem')
            ->line('Prijavite se z obstoječim računom.');
    }
}
