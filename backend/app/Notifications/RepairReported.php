<?php

namespace App\Notifications;

use App\Models\RepairRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lastnik prejme e-pošto s povezavo na novo prijavo okvare (US-09).
 */
class RepairReported extends Notification implements ShouldQueue
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
        $repair = $this->repair->loadMissing('apartment', 'sharedSpace', 'reporter', 'category');

        return (new MailMessage)
            ->subject("Nova prijava okvare {$repair->number()}: {$repair->title}")
            ->line("{$repair->reporter->name} je prijavil okvaro »{$repair->title}«.")
            ->line("Lokacija: {$repair->locationLabel()} · vrsta: {$repair->category->name}")
            ->action('Odpri prijavo', rtrim(config('app.frontend_url'), '/').'/okvare/'.$repair->id);
    }
}
