<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChargeResource;
use App\Models\Charge;
use App\Services\ChargeSourceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChargeController extends Controller
{
    /**
     * Obveznosti prijavljenega najemnika, združene po mesecu obračuna (US-04, #103).
     */
    public function mine(Request $request): JsonResponse
    {
        $buildingId = $request->integer('building_id') ?: null;

        $charges = Charge::query()
            ->whereHas('leaseTenant', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($buildingId, fn ($q) => $q->inBuilding($buildingId))
            ->orderByDesc('period')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $months = $charges->groupBy(fn (Charge $c) => $c->period->toDateString())
            ->map(fn ($items, $period) => [
                'period' => $period,
                'total_cents' => $items->sum('amount_cents'),
                'open_cents' => $items->reject->isPaid()->sum('amount_cents'),
                'charges' => ChargeResource::collection($items)->resolve(),
            ])
            ->values();

        return response()->json(['data' => $months]);
    }

    public function show(Charge $charge): ChargeResource
    {
        Gate::authorize('view', $charge);

        return ChargeResource::make($charge->load('leaseTenant.user', 'leaseTenant.lease.apartment'));
    }

    /** Izvor obveznosti (položnica, rezervacija ali prijava okvare). */
    public function source(Charge $charge, ChargeSourceResolver $resolver): JsonResponse
    {
        Gate::authorize('view', $charge);

        return response()->json(['data' => $resolver->resolve($charge)]);
    }
}
