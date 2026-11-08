<?php

namespace App\Services;

use App\Enums\ChargeType;
use App\Enums\LeaseStatus;
use App\Enums\SplitMethod;
use App\Enums\UtilityType;
use App\Models\Apartment;
use App\Models\BillShare;
use App\Models\Building;
use App\Models\Charge;
use App\Models\LeaseTenant;
use App\Models\User;
use App\Models\UtilityBill;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Delitev položnic (US-06, US-07, ADR-0007).
 *
 * Vsi zneski so v centih. Vsota deležev je vedno natanko enaka znesku položnice:
 * ostanek centov dobijo deleži z največjo utežjo (dnevi bivanja), pri enaki uteži
 * pa tisti, ki je bil dodan prvi.
 */
class BillSplitter
{
    /**
     * Dnevi bivanja sostanovalcev v obdobju, vključno z dnem vselitve in izselitve (#110).
     *
     * @return Collection<int, array{tenant: LeaseTenant, days: int}>
     */
    public function tenantDays(Apartment $apartment, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $start = CarbonImmutable::parse($start->toDateString());
        $end = CarbonImmutable::parse($end->toDateString());

        return LeaseTenant::query()
            ->whereHas('lease', fn ($q) => $q->where('apartment_id', $apartment->id)->where('status', '!=', LeaseStatus::Draft))
            ->whereDate('moved_in_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('moved_out_on')->orWhereDate('moved_out_on', '>=', $start))
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map(function (LeaseTenant $tenant) use ($start, $end) {
                $from = $start->max(CarbonImmutable::parse($tenant->moved_in_on->toDateString()));
                $to = $tenant->moved_out_on ? $end->min(CarbonImmutable::parse($tenant->moved_out_on->toDateString())) : $end;

                return ['tenant' => $tenant, 'days' => (int) $from->diffInDays($to) + 1];
            })
            ->filter(fn ($row) => $row['days'] > 0)
            ->values();
    }

    /**
     * Razdeli znesek sorazmerno z utežmi na cele cente (#111).
     * Ostanek centov dobijo elementi z največjo utežjo, pri enaki uteži po vrstnem redu.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    public function distribute(int $amountCents, array $weights): array
    {
        $total = array_sum($weights);
        if ($total === 0) {
            return array_fill(0, count($weights), 0);
        }

        $amounts = array_map(fn (int $w) => intdiv($amountCents * $w, $total), $weights);
        $remainder = $amountCents - array_sum($amounts);

        $order = array_keys($weights);
        usort($order, fn ($a, $b) => [$weights[$b], $a] <=> [$weights[$a], $b]);

        foreach (array_slice($order, 0, $remainder) as $index) {
            $amounts[$index]++;
        }

        return $amounts;
    }

    /**
     * Delitev zneska stanovanja med sostanovalce po dnevih bivanja (US-06).
     * Če v obdobju ni živel nihče, celoten znesek ostane lastniku.
     *
     * @return array{apartment: Apartment, amount_cents: int, days: int, shares: list<array{tenant: ?LeaseTenant, days: int, amount_cents: int}>}
     */
    public function splitApartment(Apartment $apartment, int $amountCents, CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = $this->tenantDays($apartment, $start, $end);

        if ($rows->isEmpty()) {
            return [
                'apartment' => $apartment,
                'amount_cents' => $amountCents,
                'days' => 0,
                'shares' => [['tenant' => null, 'days' => 0, 'amount_cents' => $amountCents]],
            ];
        }

        $amounts = $this->distribute($amountCents, $rows->pluck('days')->all());

        return [
            'apartment' => $apartment,
            'amount_cents' => $amountCents,
            'days' => $rows->sum('days'),
            'shares' => $rows->values()->map(fn ($row, $i) => [
                'tenant' => $row['tenant'],
                'days' => $row['days'],
                'amount_cents' => $amounts[$i],
            ])->all(),
        ];
    }

    /**
     * Načrt delitve položnice (predogled, ki se nato shrani).
     *
     * @return list<array{apartment: Apartment, amount_cents: int, days: int, shares: list<array{tenant: ?LeaseTenant, days: int, amount_cents: int}>}>
     */
    public function plan(Building $building, ?Apartment $apartment, int $amountCents, CarbonInterface $start, CarbonInterface $end, ?SplitMethod $method = null): array
    {
        if ($apartment) {
            return [$this->splitApartment($apartment, $amountCents, $start, $end)];
        }

        return $this->planBuilding($building, $amountCents, $start, $end, $method ?? SplitMethod::Equal);
    }

    /**
     * Shrani položnico, deleže in obveznosti v eni transakciji (#111).
     * Deleži najemnikov postanejo obveznosti vrste »stroški« v obračunu naslednjega meseca.
     *
     * @param  array{apartment_id?: ?int, type: string, amount_cents: int, period_start: string, period_end: string, split_method?: ?string}  $data
     */
    public function store(Building $building, User $author, array $data): UtilityBill
    {
        $apartment = isset($data['apartment_id']) ? $building->apartments()->findOrFail($data['apartment_id']) : null;
        $start = CarbonImmutable::parse($data['period_start']);
        $end = CarbonImmutable::parse($data['period_end']);
        $method = $apartment ? null : SplitMethod::from($data['split_method'] ?? SplitMethod::Equal->value);

        return DB::transaction(function () use ($building, $author, $data, $apartment, $start, $end, $method) {
            $bill = UtilityBill::create([
                'building_id' => $building->id,
                'apartment_id' => $apartment?->id,
                'type' => $data['type'],
                'amount_cents' => $data['amount_cents'],
                'period_start' => $start,
                'period_end' => $end,
                'split_method' => $method,
                'created_by' => $author->id,
            ]);

            $period = Dates::nextPeriod(today());
            $dueDate = Dates::dueDate($period, $building->rent_due_day);
            $description = $this->chargeDescription($bill);

            foreach ($this->plan($building, $apartment, $bill->amount_cents, $start, $end, $method) as $row) {
                foreach ($row['shares'] as $share) {
                    $billShare = BillShare::create([
                        'utility_bill_id' => $bill->id,
                        'apartment_id' => $row['apartment']->id,
                        'lease_tenant_id' => $share['tenant']?->id,
                        'days' => $share['days'],
                        'amount_cents' => $share['amount_cents'],
                    ]);

                    // Delež lastnika in ničelni deleži ne postanejo obveznost.
                    if ($share['tenant'] && $share['amount_cents'] > 0) {
                        Charge::create([
                            'lease_tenant_id' => $share['tenant']->id,
                            'type' => ChargeType::Utility,
                            'description' => $description.' ('.$row['apartment']->label.')',
                            'amount_cents' => $share['amount_cents'],
                            'period' => $period,
                            'due_date' => $dueDate,
                            'bill_share_id' => $billShare->id,
                        ]);
                    }
                }
            }

            return $bill->load('shares');
        });
    }

    /**
     * Delitev položnice stavbe med stanovanja (US-07, #113, #114):
     * - po površini: sorazmerno s kvadraturo,
     * - po številu oseb: sorazmerno z dnevi bivanja vseh najemnikov stanovanja,
     * - na enake dele: enako za vsako stanovanje.
     * Delež stanovanja se nato razdeli med sostanovalce po dnevih bivanja (kot US-06),
     * delež praznega stanovanja ostane lastniku.
     *
     * @return list<array{apartment: Apartment, amount_cents: int, days: int, shares: list<array>}>
     */
    protected function planBuilding(Building $building, int $amountCents, CarbonInterface $start, CarbonInterface $end, SplitMethod $method): array
    {
        $apartments = $building->apartments()->orderBy('label')->get();

        if ($method === SplitMethod::Area && $apartments->contains(fn (Apartment $a) => (float) $a->area_m2 <= 0)) {
            throw ValidationException::withMessages([
                'split_method' => 'Vsa stanovanja morajo imeti vneseno površino za delitev po površini.',
            ]);
        }

        $weights = $apartments->map(fn (Apartment $apartment) => match ($method) {
            SplitMethod::Area => (int) round((float) $apartment->area_m2 * 100),
            SplitMethod::Persons => $this->tenantDays($apartment, $start, $end)->sum('days'),
            SplitMethod::Equal => 1,
        })->all();

        // Če v obdobju ni živel nihče, se znesek razdeli na enake dele in ostane lastniku.
        if (array_sum($weights) === 0) {
            $weights = array_fill(0, count($weights), 1);
        }

        $amounts = $this->distribute($amountCents, $weights);

        return $apartments->values()
            ->map(fn (Apartment $apartment, int $i) => $this->splitApartment($apartment, $amounts[$i], $start, $end))
            // Prazna stanovanja z ničelnim deležem (delitev po osebah) ne potrebujejo zapisa.
            ->reject(fn (array $row) => $row['amount_cents'] === 0 && $row['days'] === 0)
            ->values()
            ->all();
    }

    private function chargeDescription(UtilityBill $bill): string
    {
        $type = $bill->type instanceof UtilityType ? $bill->type : UtilityType::from($bill->type);

        return sprintf('%s %s – %s', $type->label(), Dates::format($bill->period_start), Dates::format($bill->period_end));
    }
}
