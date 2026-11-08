<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Lease;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ApartmentController extends Controller
{
    /** Stanovanja stavbe s trenutnim stanjem zasedenosti (za obrazce in izbire). */
    public function index(Building $building): JsonResponse
    {
        Gate::authorize('manage', $building);

        $occupying = Lease::occupying()
            ->whereIn('apartment_id', $building->apartments()->select('id'))
            ->get()
            ->keyBy('apartment_id');

        $apartments = $building->apartments()->orderBy('label')->get()->map(fn ($apartment) => [
            'id' => $apartment->id,
            'label' => $apartment->label,
            'floor' => $apartment->floor,
            'area_m2' => (float) $apartment->area_m2,
            'rooms' => $apartment->rooms,
            'occupancy' => match ($occupying->get($apartment->id)?->status) {
                LeaseStatus::Active => 'occupied',
                LeaseStatus::InNotice => 'in_notice',
                default => 'vacant',
            },
            'current_lease_id' => $occupying->get($apartment->id)?->id,
        ]);

        return response()->json(['data' => $apartments]);
    }
}
