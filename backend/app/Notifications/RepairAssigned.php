<?php

namespace App\Notifications;

use App\Models\RepairRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Izvajalec prejme e-pošto s povezavo na nalog (US-10, #123).
 */
class RepairAssigned extends Notification implements ShouldQueue
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
        $repair = $this->repair->loadMissing('building', 'apartment', 'sharedSpace', 'category');

        return (new MailMessage)
            ->subject("Nov nalog {$repair->number()}: {$repair->title}")
            ->line("Dodeljen vam je nalog za popravilo »{$repair->title}« ({$repair->category->name}).")
            ->line("Lokacija: {$repair->building->name}, {$repair->building->address} · {$repair->locationLabel()}")
            ->action('Odpri nalog', rtrim(config('app.frontend_url'), '/').'/okvare/'.$repair->id)
            ->line('Prosimo, sporočite termin izvedbe.');
    }
}
