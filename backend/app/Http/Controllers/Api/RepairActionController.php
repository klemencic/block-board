<?php

namespace App\Http\Controllers\Api;

use App\Enums\Payer;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\RepairRequestResource;
use App\Models\Building;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\RepairWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Koraki obravnave prijave okvare kot namenske končne točke (ADR-0006).
 */
class RepairActionController extends Controller
{
    private const RELATIONS = ['category', 'apartment', 'sharedSpace', 'reporter', 'contractor', 'building', 'attachments', 'events.user'];

    public function __construct(private readonly RepairWorkflow $workflow) {}

    /** Izvajalci, povabljeni v stavbo (US-10). */
    public function contractors(Building $building): JsonResponse
    {
        Gate::authorize('manage', $building);

        return response()->json(['data' => $building->members()
            ->wherePivot('role', Role::Contractor->value)
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.phone'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'phone' => $u->phone])]);
    }

    /**
     * Potrdi prijavo; po izbiri hkrati spremeni plačnika in dodeli izvajalca (WF-03: »Potrdi in dodeli izvajalcu«).
     */
    public function confirm(Request $request, RepairRequest $repair): RepairRequestResource
    {
        Gate::authorize('manage', $repair);
        $data = $request->validate([
            'payer' => ['nullable', Rule::enum(Payer::class)],
            'contractor_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [], ['contractor_id' => 'izvajalec', 'payer' => 'plačnik']);

        $contractor = isset($data['contractor_id']) ? User::find($data['contractor_id']) : null;
        if ($contractor && ! $contractor->hasRole($repair->building_id, Role::Contractor)) {
            abort(422, 'Izberete lahko samo izvajalca, povabljenega v stavbo.');
        }

        $this->workflow->confirm($repair, $request->user());
        if (isset($data['payer'])) {
            $this->workflow->changePayer($repair, $request->user(), Payer::from($data['payer']));
        }
        if ($contractor) {
            $this->workflow->assign($repair, $request->user(), $contractor);
        }

        return $this->respond($repair);
    }

    public function reject(Request $request, RepairRequest $repair): RepairRequestResource
    {
        Gate::authorize('manage', $repair);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], [
            'reason.required' => 'Pri zavrnitvi je obrazložitev obvezna.',
        ]);

        $this->workflow->reject($repair, $request->user(), $data['reason']);

        return $this->respond($repair);
    }

    public function payer(Request $request, RepairRequest $repair): RepairRequestResource
    {
        Gate::authorize('manage', $repair);
        $data = $request->validate(['payer' => ['required', Rule::enum(Payer::class)]], [], ['payer' => 'plačnik']);

        $this->workflow->changePayer($repair, $request->user(), Payer::from($data['payer']));

        return $this->respond($repair);
    }

    public function assign(Request $request, RepairRequest $repair): RepairRequestResource
    {
        Gate::authorize('manage', $repair);
        $data = $request->validate(['contractor_id' => ['required', 'integer', 'exists:users,id']], [], ['contractor_id' => 'izvajalec']);

        $this->workflow->assign($repair, $request->user(), User::findOrFail($data['contractor_id']));

        return $this->respond($repair);
    }

    private function respond(RepairRequest $repair): RepairRequestResource
    {
        return RepairRequestResource::make($repair->refresh()->load(self::RELATIONS));
    }
}
