<?php

namespace App\Services;

use App\Enums\ChargeType;
use App\Enums\LeaseStatus;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\LeaseTenant;
use App\Models\User;
use App\Notifications\MonthlyStatement;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesečno ustvarjanje obveznosti za najemnino (US-03, ADR-0007).
 *
 * Postopek je idempotenten: vsak najemnik ima za posamezen mesec največ eno obveznost
 * za najemnino, kar dodatno zagotavlja delni enolični indeks v bazi.
 */
class ChargeGenerator
{
    /**
     * Ustvari manjkajoče obveznosti za najemnino v mesecu podanega dne.
     *
     * @return int število novih obveznosti
     */
    public function generate(?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::parse(($today ?? today())->toDateString());

        return Lease::occupying()
            ->with(['apartment.building', 'tenants'])
            ->get()
            ->sum(fn (Lease $lease) => $this->generateForLease($lease, $today));
    }

    /**
     * Obveznosti za najemnino enega najema v mesecu podanega dne.
     * Najemnik, ki se vseli med mesecem, dobi sorazmerni del najemnine.
     */
    public function generateForLease(Lease $lease, ?CarbonInterface $today = null): int
    {
        $today = CarbonImmutable::parse(($today ?? today())->toDateString());
        $period = $today->startOfMonth();

        if (! in_array($lease->status->value, LeaseStatus::occupying(), true)) {
            return 0;
        }

        $created = 0;

        foreach ($lease->tenants as $tenant) {
            if (! $this->livesInPeriod($tenant, $period, $today)) {
                continue;
            }

            if ($this->hasRentCharge($tenant, $period)) {
                continue;
            }

            try {
                Charge::create($this->rentCharge($lease, $tenant, $period));
                $created++;
            } catch (UniqueConstraintViolationException) {
                // Sočasen zagon je obveznost že ustvaril.
            }
        }

        return $created;
    }

    /**
     * Sorazmerni del najemnine v centih, vključno z dnem začetka (US-03, #101).
     * Za 300,00 € in začetek 16. 5. 2027: 16/31 × 300,00 € = 154,84 €.
     */
    public function proratedAmount(int $shareCents, CarbonInterface $movedInOn): int
    {
        $daysInMonth = $movedInOn->daysInMonth;
        $days = $daysInMonth - $movedInOn->day + 1;

        // Zaokrožanje na cel cent samo s celimi števili.
        return intdiv($shareCents * $days * 2 + $daysInMonth, 2 * $daysInMonth);
    }

    /**
     * Pošlje mesečni povzetek obveznosti vsakemu najemniku natanko enkrat (US-03, #102).
     *
     * @return int število poslanih povzetkov
     */
    public function sendStatements(?CarbonInterface $today = null): int
    {
        $period = CarbonImmutable::parse(($today ?? today())->toDateString())->startOfMonth();

        $charges = Charge::query()
            ->whereDate('period', $period)
            ->with('leaseTenant.lease.apartment.building', 'leaseTenant.user')
            ->get();

        $sent = 0;

        $charges
            ->groupBy(fn (Charge $c) => $c->leaseTenant->user_id.'-'.$c->leaseTenant->lease->apartment->building_id)
            ->each(function (Collection $items) use ($period, &$sent) {
                $tenant = $items->first()->leaseTenant;
                $building = $tenant->lease->apartment->building;

                $inserted = DB::table('monthly_statements')->insertOrIgnore([
                    'user_id' => $tenant->user_id,
                    'building_id' => $building->id,
                    'period' => $period->toDateString(),
                    'sent_at' => now(),
                ]);

                if ($inserted === 1) {
                    $tenant->user->notify(new MonthlyStatement($building, $period, $items->sortBy('id')->values()));
                    $sent++;
                }
            });

        return $sent;
    }

    private function livesInPeriod(LeaseTenant $tenant, CarbonImmutable $period, CarbonImmutable $today): bool
    {
        // Prihodnji začetki in izseljeni sostanovalci ne dobijo obveznosti.
        if ($tenant->moved_in_on->gt($today)) {
            return false;
        }

        return $tenant->moved_out_on === null || $tenant->moved_out_on->gte($period);
    }

    private function hasRentCharge(LeaseTenant $tenant, CarbonImmutable $period): bool
    {
        return Charge::where('lease_tenant_id', $tenant->id)
            ->where('type', ChargeType::Rent)
            ->whereDate('period', $period)
            ->exists();
    }

    private function rentCharge(Lease $lease, LeaseTenant $tenant, CarbonImmutable $period): array
    {
        $movedIn = CarbonImmutable::parse($tenant->moved_in_on->toDateString());
        $dueDate = Dates::dueDate($period, $lease->apartment->building->rent_due_day);

        if ($movedIn->gt($period)) {
            $amount = $this->proratedAmount($tenant->rent_share_cents, $movedIn);
            $description = sprintf(
                'Najemnina %s (sorazmerno %d.–%d. %d.)',
                Dates::period($period),
                $movedIn->day,
                $period->daysInMonth,
                $period->month
            );
            // Rok ni pred dnem vselitve.
            $dueDate = $dueDate->max($movedIn);
        } else {
            $amount = $tenant->rent_share_cents;
            $description = 'Najemnina '.Dates::period($period);
        }

        return [
            'lease_tenant_id' => $tenant->id,
            'type' => ChargeType::Rent,
            'description' => $description,
            'amount_cents' => $amount,
            'period' => $period,
            'due_date' => $dueDate,
        ];
    }
}
