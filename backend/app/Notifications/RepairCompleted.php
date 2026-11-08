<?php

namespace App\Notifications;

use App\Models\RepairRequest;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lastnik prejme e-pošto, ko izvajalec odda poročilo o opravljenem delu (US-11, #126).
 */
class RepairCompleted extends Notification implements ShouldQueue
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
            ->subject("Popravilo opravljeno {$repair->number()}: {$repair->title}")
            ->line("{$repair->contractor->name} je oddal poročilo o opravljenem delu.")
            ->line('Strošek: '.Money::format($repair->cost_cents ?? 0))
            ->action('Preglej in zaključi', rtrim(config('app.frontend_url'), '/').'/okvare/'.$repair->id);
    }
}
