<?php

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Enums\RepairStatus;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\RepairRequest;
use Carbon\CarbonImmutable;

/**
 * Pregled stavbe za lastnika (US-13, WF-01).
 */
class BuildingOverview
{
    /** Najem, ki se konča v toliko dneh, ima oznako »Izteka se«. */
    public const EXPIRING_DAYS = 60;

    public function build(Building $building): array
    {
        $today = CarbonImmutable::today();
        $apartments = $building->apartments()->orderBy('label')->get();
        $apartmentIds = $apartments->pluck('id');

        $leases = Lease::occupying()
            ->whereIn('apartment_id', $apartmentIds)
            ->with(['tenants' => fn ($q) => $q->whereNull('moved_out_on')->with('user')])
            ->get()
            ->keyBy('apartment_id');

        // Zamujene obveznosti po stanovanju (neplačane s pretečenim rokom).
        $overdue = Charge::overdue()
            ->inBuilding($building->id)
            ->with('leaseTenant.lease')
            ->get()
            ->groupBy(fn (Charge $c) => $c->leaseTenant->lease->apartment_id);

        // Odprte prijave: niso zaključene ali zavrnjene.
        $openRepairs = RepairRequest::where('building_id', $building->id)
            ->whereIn('status', RepairStatus::open())
            ->get();
        $repairsByApartment = $openRepairs->whereNotNull('apartment_id')->countBy('apartment_id');

        $rows = $apartments->map(function (Apartment $apartment) use ($leases, $overdue, $repairsByApartment, $today) {
            $lease = $leases->get($apartment->id);
            $state = match ($lease?->status) {
                LeaseStatus::Active => 'occupied',
                LeaseStatus::InNotice => 'in_notice',
                default => 'vacant',
            };
            $endDate = $lease?->status === LeaseStatus::InNotice ? $lease->terminates_on : $lease?->ends_on;
            $charges = $overdue->get($apartment->id, collect());

            return [
                'id' => $apartment->id,
                'label' => $apartment->label,
                'floor' => $apartment->floor,
                'state' => $state,
                'lease_id' => $lease?->id,
                'tenants' => $lease ? $lease->tenants->map(fn ($t) => $t->user->name)->values()->all() : [],
                'end_date' => $endDate?->toDateString(),
                'end_type' => $lease ? ($lease->status === LeaseStatus::InNotice ? 'termination' : ($lease->ends_on ? 'end' : 'indefinite')) : null,
                'expiring' => $lease?->status === LeaseStatus::Active && $lease->ends_on
                    && $lease->ends_on->betweenIncluded($today, $today->addDays(self::EXPIRING_DAYS)),
                'overdue_cents' => $charges->sum('amount_cents'),
                'overdue_count' => $charges->count(),
                'open_repairs' => $repairsByApartment->get($apartment->id, 0),
            ];
        });

        return [
            'date' => $today->toDateString(),
            'summary' => [
                'apartments_total' => $rows->count(),
                'apartments_occupied' => $rows->where('state', '!=', 'vacant')->count(),
                'overdue_cents' => $rows->sum('overdue_cents'),
                'overdue_apartments' => $rows->where('overdue_cents', '>', 0)->count(),
                'overdue_charges' => $rows->sum('overdue_count'),
                'open_repairs' => $openRepairs->count(),
                'repairs_awaiting_confirmation' => $openRepairs->where('status', RepairStatus::Reported)->count(),
                'expiring_leases' => $rows->where('expiring', true)->count(),
                'counts' => [
                    'occupied' => $rows->where('state', 'occupied')->count(),
                    'in_notice' => $rows->where('state', 'in_notice')->count(),
                    'vacant' => $rows->where('state', 'vacant')->count(),
                ],
            ],
            'apartments' => $rows->values()->all(),
        ];
    }
}
