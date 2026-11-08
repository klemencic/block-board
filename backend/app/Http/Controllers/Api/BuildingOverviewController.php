<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Enums\RepairStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChargeResource;
use App\Http\Resources\LeaseResource;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Charge;
use App\Models\RepairRequest;
use App\Services\BuildingOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BuildingOverviewController extends Controller
{
    /** Pregled stavbe (US-13). */
    public function show(Building $building, BuildingOverview $overview): JsonResponse
    {
        Gate::authorize('manage', $building);

        return response()->json(['data' => $overview->build($building)]);
    }

    /** Podrobnosti stanovanja ob kliku na vrstico pregleda (US-13, #130). */
    public function apartment(Apartment $apartment): JsonResponse
    {
        Gate::authorize('manage', $apartment->building);

        $leases = $apartment->leases()->with(['apartment', 'tenants.user'])->latest('starts_on')->get();

        return response()->json(['data' => [
            'id' => $apartment->id,
            'label' => $apartment->label,
            'floor' => $apartment->floor,
            'area_m2' => (float) $apartment->area_m2,
            'rooms' => $apartment->rooms,
            'current_lease' => ($current = $leases->first(fn ($l) => in_array($l->status->value, LeaseStatus::occupying(), true)))
                ? LeaseResource::make($current)->resolve() : null,
            'leases' => LeaseResource::collection($leases)->resolve(),
            'overdue_charges' => ChargeResource::collection(
                Charge::overdue()->whereHas('leaseTenant.lease', fn ($q) => $q->where('apartment_id', $apartment->id))
                    ->with('leaseTenant.user', 'leaseTenant.lease.apartment')->orderBy('due_date')->get()
            )->resolve(),
            'open_repairs' => RepairRequest::where('apartment_id', $apartment->id)
                ->whereIn('status', RepairStatus::open())->latest('id')->get()
                ->map(fn ($r) => ['id' => $r->id, 'number' => $r->number(), 'title' => $r->title, 'status_label' => $r->status->label()]),
        ]]);
    }

    /**
     * Številke ob postavkah menija: lastnik odprte prijave okvar, izvajalec odprti nalogi.
     */
    public function badges(Request $request, Building $building): JsonResponse
    {
        Gate::authorize('view', $building);
        $user = $request->user();
        $badges = [];

        if ($user->isLandlordOf($building)) {
            $badges['/okvare'] = RepairRequest::where('building_id', $building->id)->whereIn('status', RepairStatus::open())->count();
        } elseif ($user->hasRole($building, \App\Enums\Role::Tenant)) {
            // Neprebrana obvestila (WF: »najemnik 2 neprebrani obvestili«).
            $badges['/obvestila'] = \App\Models\Announcement::visibleToTenant($user, $building->id)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))->count();
        } elseif ($user->hasRole($building, \App\Enums\Role::Contractor)) {
            $badges['/nalogi'] = RepairRequest::where('building_id', $building->id)->where('contractor_id', $user->id)
                ->whereIn('status', [RepairStatus::Assigned->value, RepairStatus::Scheduled->value])->count();
        }

        return response()->json(['data' => $badges]);
    }
}
