<?php

namespace App\Notifications;

use App\Models\Building;
use App\Models\Charge;
use App\Support\Dates;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Mesečni povzetek obveznosti najemnika (US-03, #102).
 * Vsebuje samo obveznosti tega najemnika.
 */
class MonthlyStatement extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Charge>  $charges
     */
    public function __construct(
        public readonly Building $building,
        public readonly CarbonInterface $period,
        public readonly Collection $charges,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Obveznosti za '.Dates::period($this->period).' – '.$this->building->name)
            ->greeting('Pozdravljeni, '.$notifiable->name)
            ->line('Vaše obveznosti za '.Dates::period($this->period).':');

        foreach ($this->charges as $charge) {
            $mail->line('• '.$charge->description.': '.Money::format($charge->amount_cents)
                .' (rok '.Dates::format($charge->due_date).')');
        }

        return $mail
            ->line('Skupaj: '.Money::format($this->total()))
            ->line('Rok plačila: '.Dates::format($this->dueDate()))
            ->action('Moje obveznosti', rtrim(config('app.frontend_url'), '/').'/moje-obveznosti');
    }

    public function total(): int
    {
        return $this->charges->sum('amount_cents');
    }

    public function dueDate(): CarbonInterface
    {
        return $this->charges->min('due_date');
    }
}
