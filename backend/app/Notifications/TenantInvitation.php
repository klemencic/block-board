<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Povabilo novemu najemniku s povezavo za nastavitev gesla.
 * V URL-ju je samo enkratni žeton, brez e-naslova ali drugih osebnih podatkov.
 */
class TenantInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lease $lease, public readonly string $token)
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
        $url = rtrim(config('app.frontend_url'), '/').'/povabilo/'.$this->token;

        return (new MailMessage)
            ->subject('Povabilo v BlockBoard – '.$apartment->building->name)
            ->greeting('Pozdravljeni, '.$notifiable->name)
            ->line("Lastnik vas je dodal na najem stanovanja {$apartment->label} v stavbi {$apartment->building->name}.")
            ->line('Za dostop do najema si nastavite geslo.')
            ->action('Nastavi geslo', $url)
            ->line('Povezava velja 7 dni.');
    }
}
