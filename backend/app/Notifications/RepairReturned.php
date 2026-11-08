<?php

namespace App\Notifications;

use App\Models\RepairRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Izvajalec prejme e-pošto z obrazložitvijo vrnitve v popravek (US-12, #128).
 */
class RepairReturned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RepairRequest $repair, public readonly string $reason)
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
            ->subject("Vrnjeno v popravek {$this->repair->number()}: {$this->repair->title}")
            ->line('Lastnik je poročilo o izvedbi vrnil v popravek.')
            ->line('Obrazložitev: '.$this->reason)
            ->action('Odpri nalog', rtrim(config('app.frontend_url'), '/').'/okvare/'.$this->repair->id)
            ->line('Po dopolnitvi ponovno oddajte poročilo s stroškom in fotografijami.');
    }
}
