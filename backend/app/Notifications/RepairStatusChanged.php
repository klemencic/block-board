<?php

namespace App\Notifications;

use App\Enums\RepairStatus;
use App\Models\RepairEvent;
use App\Models\RepairRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prijavitelj prejme obvestilo ob vsaki spremembi stanja prijave (US-10, kriterij 5).
 */
class RepairStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RepairRequest $repair, public readonly RepairEvent $event)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = RepairStatus::from($this->event->to_status);

        $mail = (new MailMessage)
            ->subject("Prijava {$this->repair->number()}: {$status->label()}")
            ->line("Prijava »{$this->repair->title}« je v stanju »{$status->label()}«.");

        if ($this->event->note) {
            $mail->line(($status === RepairStatus::Rejected ? 'Obrazložitev: ' : '').$this->event->note);
        }

        return $mail->action('Odpri prijavo', rtrim(config('app.frontend_url'), '/').'/okvare/'.$this->repair->id);
    }
}
