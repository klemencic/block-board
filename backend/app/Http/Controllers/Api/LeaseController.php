<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Apartment;
use App\Models\Building;
use App\Models\Lease;
use App\Services\LeaseLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LeaseController extends Controller
{
    public function __construct(private readonly LeaseLifecycle $lifecycle) {}

    /** Vsi najemi stavbe (lastnik). */
    public function index(Building $building): AnonymousResourceCollection
    {
        Gate::authorize('manage', $building);

        $leases = Lease::query()
            ->whereHas('apartment', fn ($q) => $q->where('building_id', $building->id))
            ->with(['apartment', 'tenants.user'])
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'active' THEN 1 WHEN 'in_notice' THEN 2 ELSE 3 END")
            ->latest('starts_on')
            ->get();

        return LeaseResource::collection($leases);
    }

    /** Najemi prijavljenega najemnika. */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $leases = Lease::query()
            ->whereHas('tenants', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['apartment.building', 'tenants.user'])
            ->latest('starts_on')
            ->get();

        return LeaseResource::collection($leases);
    }

    public function store(StoreLeaseRequest $request, Apartment $apartment): JsonResponse
    {
        $lease = $this->lifecycle->create($apartment, $request->validated());

        return LeaseResource::make($lease)->response()->setStatusCode(201);
    }

    public function show(Lease $lease): LeaseResource
    {
        Gate::authorize('view', $lease);

        return LeaseResource::make($lease->load(['apartment', 'tenants.user']));
    }

    /** Najemnik potrdi odpoved najema (US-02). */
    public function giveNotice(Request $request, Lease $lease): LeaseResource
    {
        Gate::authorize('giveNotice', $lease);

        $lease = $this->lifecycle->confirmNotice($lease, $request->user());

        return LeaseResource::make($lease->load(['apartment', 'tenants.user']));
    }

    public function activate(Lease $lease): LeaseResource
    {
        Gate::authorize('activate', $lease);

        return LeaseResource::make($this->lifecycle->activate($lease)->load(['apartment', 'tenants.user']));
    }
}
