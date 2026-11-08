<?php

namespace App\Services;

use App\Enums\ChargeType;
use App\Exceptions\WorkflowException;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\LeaseTenant;
use App\Models\SharedSpace;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rezervacije skupnih prostorov (US-08, ADR-0007): obratovalni čas, tedenska omejitev
 * stanovanja, izračun cene in obračun v mesecu po terminu.
 */
class BookingService
{
    /** SQLSTATE za kršitev izključitvene omejitve v PostgreSQL. */
    private const EXCLUSION_VIOLATION = '23P01';

    public function book(SharedSpace $space, LeaseTenant $tenant, CarbonInterface $startsAt, CarbonInterface $endsAt): Booking
    {
        $startsAt = CarbonImmutable::parse($startsAt)->setSecond(0);
        $endsAt = CarbonImmutable::parse($endsAt)->setSecond(0);

        $this->validateSlot($space, $startsAt, $endsAt);

        try {
            return DB::transaction(function () use ($space, $tenant, $startsAt, $endsAt) {
                // Zaklep prostora zaporedno obdela sočasne rezervacije istega prostora.
                SharedSpace::whereKey($space->id)->lockForUpdate()->first();

                $this->ensureNoOverlap($space, $startsAt, $endsAt);
                $this->ensureWeeklyLimit($space, $tenant, $startsAt, $endsAt);

                $booking = Booking::create([
                    'shared_space_id' => $space->id,
                    'lease_tenant_id' => $tenant->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'fee_cents' => $this->fee($space, $startsAt, $endsAt),
                ]);

                // Pri brezplačnem prostoru obveznost ne nastane.
                if ($booking->fee_cents > 0) {
                    $period = Dates::nextPeriod($startsAt);
                    Charge::create([
                        'lease_tenant_id' => $tenant->id,
                        'type' => ChargeType::Booking,
                        'description' => sprintf('%s %s %s–%s', $space->name, Dates::format($startsAt), $startsAt->format('H:i'), $endsAt->format('H:i')),
                        'amount_cents' => $booking->fee_cents,
                        'period' => $period,
                        'due_date' => Dates::dueDate($period, $space->building->rent_due_day),
                        'booking_id' => $booking->id,
                    ]);
                }

                return $booking;
            });
        } catch (QueryException $e) {
            // Varovalka v bazi: drugo rezervacijo je prehitela sočasna zahteva.
            if ($e->getCode() === self::EXCLUSION_VIOLATION) {
                throw new WorkflowException('Termin je že zaseden.');
            }
            throw $e;
        }
    }

    /** Cena = trajanje × cena na uro, na cel cent. */
    public function fee(SharedSpace $space, CarbonInterface $startsAt, CarbonInterface $endsAt): int
    {
        $minutes = (int) $startsAt->diffInMinutes($endsAt);

        return intdiv($space->price_per_hour_cents * $minutes + 30, 60);
    }

    /**
     * Ure, ki jih je stanovanje že porabilo v koledarskem tednu (pon–ned) termina.
     * Štejejo se rezervacije vseh sostanovalcev.
     */
    public function usedMinutes(SharedSpace $space, LeaseTenant $tenant, CarbonInterface $day): int
    {
        $weekStart = CarbonImmutable::parse($day)->startOfWeek(CarbonInterface::MONDAY);

        return (int) Booking::active()
            ->where('shared_space_id', $space->id)
            ->whereIn('lease_tenant_id', LeaseTenant::where('lease_id', $tenant->lease_id)->select('id'))
            ->where('starts_at', '>=', $weekStart)
            ->where('starts_at', '<', $weekStart->addWeek())
            ->get()
            ->sum(fn (Booking $b) => $b->minutes());
    }

    private function validateSlot(SharedSpace $space, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if (! $space->is_active) {
            $fail('starts_at', 'Prostor trenutno ni na voljo za rezervacije.');
        }
        foreach (['starts_at' => $startsAt, 'ends_at' => $endsAt] as $field => $time) {
            if (! in_array($time->minute, [0, 30], true)) {
                $fail($field, 'Rezervacija se začne in konča ob polni ali pol ure.');
            }
        }
        if ($endsAt->lte($startsAt)) {
            $fail('ends_at', 'Konec mora biti po začetku.');
        }
        if ($startsAt->lt(now())) {
            $fail('starts_at', 'Termin se ne sme začeti v preteklosti.');
        }

        $openFrom = $startsAt->setTimeFromTimeString($space->open_from);
        $openTo = $startsAt->setTimeFromTimeString($space->open_to);
        if (! $startsAt->isSameDay($endsAt) || $startsAt->lt($openFrom) || $endsAt->gt($openTo)) {
            $fail('starts_at', sprintf('Termin mora biti v celoti znotraj obratovalnega časa (%s–%s).',
                substr($space->open_from, 0, 5), substr($space->open_to, 0, 5)));
        }
    }

    private function ensureNoOverlap(SharedSpace $space, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $overlaps = Booking::active()
            ->where('shared_space_id', $space->id)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlaps) {
            throw new WorkflowException('Termin je že zaseden.');
        }
    }

    private function ensureWeeklyLimit(SharedSpace $space, LeaseTenant $tenant, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $limit = $space->max_hours_per_week * 60;
        $available = max(0, $limit - $this->usedMinutes($space, $tenant, $startsAt));
        $requested = (int) $startsAt->diffInMinutes($endsAt);

        if ($requested > $available) {
            throw ValidationException::withMessages([
                'ends_at' => sprintf(
                    'Rezervacija presega tedensko omejitev stanovanja (%d h). Ta teden je na voljo še %s h.',
                    $space->max_hours_per_week,
                    rtrim(rtrim(number_format($available / 60, 1, ',', ''), '0'), ',')
                ),
            ]);
        }
    }
}
