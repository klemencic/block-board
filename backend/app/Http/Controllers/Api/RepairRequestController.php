<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRepairRequest;
use App\Http\Resources\RepairRequestResource;
use App\Models\Building;
use App\Models\LeaseTenant;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Services\RepairWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RepairRequestController extends Controller
{
    private const RELATIONS = ['category', 'apartment', 'sharedSpace', 'reporter', 'contractor', 'building', 'attachments'];

    public function __construct(private readonly RepairWorkflow $workflow) {}

    /** Prijave, ki jih uporabnik sme videti, z novejšimi na vrhu. */
    public function index(Request $request, Building $building): AnonymousResourceCollection
    {
        Gate::authorize('view', $building);

        $repairs = RepairRequest::visibleTo($request->user(), $building->id)
            ->with(self::RELATIONS)
            ->latest('id')
            ->get();

        return RepairRequestResource::collection($repairs);
    }

    public function store(StoreRepairRequest $request, Building $building): JsonResponse
    {
        $repair = $this->workflow->report($building, $request->user(), $request->safe()->except('photos'), $request->file('photos', []));

        return RepairRequestResource::make($repair->load([...self::RELATIONS, 'events.user']))->response()->setStatusCode(201);
    }

    public function show(RepairRequest $repair): RepairRequestResource
    {
        Gate::authorize('view', $repair);

        return RepairRequestResource::make($repair->load([...self::RELATIONS, 'events.user']));
    }

    /**
     * Podatki za obrazec prijave: vrste okvar s privzetim plačnikom in dovoljene lokacije.
     */
    public function form(Request $request, Building $building): JsonResponse
    {
        Gate::authorize('view', $building);
        $user = $request->user();

        $apartments = $user->isLandlordOf($building)
            ? $building->apartments()->orderBy('label')->get()
            : LeaseTenant::where('user_id', $user->id)
                ->whereNull('moved_out_on')
                ->whereHas('lease', fn ($q) => $q->whereIn('status', LeaseStatus::occupying())
                    ->whereHas('apartment', fn ($q) => $q->where('building_id', $building->id)))
                ->with('lease.apartment')
                ->get()
                ->map(fn ($t) => $t->lease->apartment);

        return response()->json(['data' => [
            'categories' => RepairCategory::orderBy('id')->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'default_payer' => $c->default_payer->value,
                'default_payer_label' => $c->default_payer->label(),
            ]),
            'apartments' => $apartments->map(fn ($a) => ['id' => $a->id, 'label' => $a->label, 'floor' => $a->floor])->values(),
            'shared_spaces' => $building->sharedSpaces()->orderBy('id')->get(['id', 'name']),
            'max_photos' => StoreRepairRequest::MAX_PHOTOS,
            'max_photo_mb' => StoreRepairRequest::MAX_KB / 1024,
        ]]);
    }
}
