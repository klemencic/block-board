<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChargeType;
use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChargeResource;
use App\Models\Building;
use App\Models\Charge;
use App\Models\LeaseTenant;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Obveznosti stavbe za lastnika (US-05).
 */
class BuildingChargeController extends Controller
{
    /** Seznam obveznosti s filtri po mesecu, stanovanju in stanju (#108). */
    public function index(Request $request, Building $building): AnonymousResourceCollection
    {
        Gate::authorize('manage', $building);

        $filters = $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'apartment_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['open', 'paid', 'overdue'])],
        ]);

        $charges = Charge::query()
            ->inBuilding($building->id)
            ->with('leaseTenant.user', 'leaseTenant.lease.apartment')
            ->when($filters['period'] ?? null, fn ($q, $period) => $q->whereDate('period', $period.'-01'))
            ->when($filters['apartment_id'] ?? null, fn ($q, $id) => $q->whereHas('leaseTenant.lease', fn ($q) => $q->where('apartment_id', $id)))
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'paid' => $q->whereNotNull('paid_at'),
                'overdue' => $q->overdue(),
                'open' => $q->whereNull('paid_at'),
            })
            ->orderByDesc('period')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        return ChargeResource::collection($charges);
    }

    /** Mesci obračuna, za katere v stavbi obstajajo obveznosti (za filter). */
    public function periods(Building $building): JsonResponse
    {
        Gate::authorize('manage', $building);

        $periods = Charge::query()->inBuilding($building->id)
            ->select('period')->distinct()->orderByDesc('period')->pluck('period')
            ->map(fn ($p) => $p->format('Y-m'));

        return response()->json(['data' => $periods]);
    }

    /** Najemniki stavbe, ki jim lahko lastnik doda obveznost (popravek ali dobropis). */
    public function tenants(Building $building): JsonResponse
    {
        Gate::authorize('manage', $building);

        $tenants = LeaseTenant::query()
            ->whereHas('lease.apartment', fn ($q) => $q->where('building_id', $building->id))
            ->whereHas('lease', fn ($q) => $q->where('status', '!=', 'draft'))
            ->with('user', 'lease.apartment')
            ->get()
            ->sortBy(fn ($t) => [$t->lease->apartment->label, $t->id])
            ->values()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->user->name,
                'apartment' => $t->lease->apartment->label,
                'lease_status' => $t->lease->status->value,
                'moved_out_on' => $t->moved_out_on?->toDateString(),
            ]);

        return response()->json(['data' => $tenants]);
    }

    /**
     * Popravek kot nova obveznost vrste »drugo«; negativen znesek je dobropis (#107).
     */
    public function store(Request $request, Building $building): JsonResponse
    {
        Gate::authorize('manage', $building);

        $data = $request->validate([
            'lease_tenant_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'not_in:0'],
            'period' => ['nullable', 'date_format:Y-m'],
        ], [
            'amount_cents.not_in' => 'Znesek ne sme biti 0.',
        ], [
            'lease_tenant_id' => 'najemnik',
            'description' => 'opis',
            'amount_cents' => 'znesek',
        ]);

        $tenant = LeaseTenant::whereKey($data['lease_tenant_id'])
            ->whereHas('lease.apartment', fn ($q) => $q->where('building_id', $building->id))
            ->first();

        if (! $tenant) {
            abort(422, 'Izbrani najemnik ne pripada tej stavbi.');
        }

        $period = isset($data['period'])
            ? CarbonImmutable::parse($data['period'].'-01')
            : CarbonImmutable::today()->startOfMonth();

        $charge = Charge::create([
            'lease_tenant_id' => $tenant->id,
            'type' => ChargeType::Other,
            'description' => $data['description'],
            'amount_cents' => $data['amount_cents'],
            'period' => $period,
            'due_date' => Dates::dueDate($period, $building->rent_due_day),
        ]);

        return ChargeResource::make($charge->load('leaseTenant.user', 'leaseTenant.lease.apartment'))
            ->response()->setStatusCode(201);
    }

    /**
     * Lastnik zabeleži prejem plačila (#106). Datum je privzeto današnji in ne sme biti v prihodnosti.
     */
    public function markPaid(Request $request, Charge $charge): ChargeResource
    {
        Gate::authorize('markPaid', $charge);

        $data = $request->validate([
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'paid_on.before_or_equal' => 'Datum plačila ne sme biti v prihodnosti.',
        ], ['paid_on' => 'datum plačila']);

        if ($charge->isPaid()) {
            throw new WorkflowException('Obveznost je že označena kot plačana.');
        }

        $charge->update(['paid_at' => CarbonImmutable::parse($data['paid_on'] ?? today())->startOfDay()]);

        return ChargeResource::make($charge->load('leaseTenant.user', 'leaseTenant.lease.apartment'));
    }
}
