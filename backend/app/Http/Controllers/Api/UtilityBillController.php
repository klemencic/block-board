<?php

namespace App\Http\Controllers\Api;

use App\Enums\SplitMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\UtilityBillRequest;
use App\Models\Building;
use App\Models\UtilityBill;
use App\Services\BillSplitter;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UtilityBillController extends Controller
{
    public function __construct(private readonly BillSplitter $splitter) {}

    /** Zadnje položnice stavbe. */
    public function index(Building $building): JsonResponse
    {
        Gate::authorize('manage', $building);

        $bills = UtilityBill::where('building_id', $building->id)
            ->with('apartment')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (UtilityBill $bill) => $this->billData($bill));

        return response()->json(['data' => $bills]);
    }

    /** Predogled deležev pred shranjevanjem (US-06, US-07). */
    public function preview(UtilityBillRequest $request, Building $building): JsonResponse
    {
        $data = $request->billData();
        $apartment = $data['apartment_id'] ? $building->apartments()->findOrFail($data['apartment_id']) : null;

        $plan = $this->splitter->plan(
            $building,
            $apartment,
            $data['amount_cents'],
            CarbonImmutable::parse($data['period_start']),
            CarbonImmutable::parse($data['period_end']),
            $data['split_method'] ? SplitMethod::from($data['split_method']) : null,
        );

        $total = collect($plan)->flatMap(fn ($row) => $row['shares'])->sum('amount_cents');
        $nextPeriod = Dates::nextPeriod(today());

        return response()->json(['data' => [
            'days_in_period' => (int) CarbonImmutable::parse($data['period_start'])->diffInDays(CarbonImmutable::parse($data['period_end'])) + 1,
            'total_cents' => $total,
            'matches_bill' => $total === $data['amount_cents'],
            'charge_period' => $nextPeriod->toDateString(),
            'charge_period_label' => Dates::monthName($nextPeriod),
            'apartments' => collect($plan)->map(fn ($row) => [
                'id' => $row['apartment']->id,
                'label' => $row['apartment']->label,
                'floor' => $row['apartment']->floor,
                'area_m2' => (float) $row['apartment']->area_m2,
                'amount_cents' => $row['amount_cents'],
                'days' => $row['days'],
                'landlord_share' => $row['shares'][0]['tenant'] === null,
                'shares' => collect($row['shares'])->map(fn ($share) => [
                    'lease_tenant_id' => $share['tenant']?->id,
                    'name' => $share['tenant']?->user->name ?? 'Lastnik (prazno stanovanje)',
                    'moved_in_on' => $share['tenant']?->moved_in_on->toDateString(),
                    'moved_out_on' => $share['tenant']?->moved_out_on?->toDateString(),
                    'days' => $share['days'],
                    'amount_cents' => $share['amount_cents'],
                ])->all(),
            ])->all(),
        ]]);
    }

    /** Shrani položnico, deleže in obveznosti v eni transakciji. */
    public function store(UtilityBillRequest $request, Building $building): JsonResponse
    {
        $bill = $this->splitter->store($building, $request->user(), $request->billData());

        return response()->json(['data' => $this->billData($bill->load('apartment'))], 201);
    }

    private function billData(UtilityBill $bill): array
    {
        return [
            'id' => $bill->id,
            'type' => $bill->type->value,
            'type_label' => $bill->type->label(),
            'scope' => $bill->isForBuilding() ? 'building' : 'apartment',
            'apartment' => $bill->apartment ? ['id' => $bill->apartment->id, 'label' => $bill->apartment->label] : null,
            'amount_cents' => $bill->amount_cents,
            'period_start' => $bill->period_start->toDateString(),
            'period_end' => $bill->period_end->toDateString(),
            'split_method' => $bill->split_method?->value,
            'split_method_label' => $bill->split_method?->label(),
            'created_at' => $bill->created_at,
        ];
    }
}
