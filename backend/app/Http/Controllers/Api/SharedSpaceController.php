<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Building;
use App\Models\LeaseTenant;
use App\Models\SharedSpace;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SharedSpaceController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    /** Skupni prostori stavbe. */
    public function index(Building $building): JsonResponse
    {
        Gate::authorize('view', $building);

        return response()->json(['data' => $building->sharedSpaces()->where('is_active', true)->orderBy('id')->get()
            ->map(fn (SharedSpace $space) => $this->spaceData($space))]);
    }

    /**
     * Tedenski pregled zasedenosti (US-08, #115). Imena drugih najemnikov niso vključena.
     */
    public function availability(Request $request, SharedSpace $space): JsonResponse
    {
        Gate::authorize('view', $space->building);

        $request->validate(['week' => ['nullable', 'date']]);
        $weekStart = CarbonImmutable::parse($request->input('week', today()->toDateString()))->startOfWeek(CarbonInterface::MONDAY);
        $user = $request->user();
        $isLandlord = $user->isLandlordOf($space->building_id);
        $tenant = $this->currentTenant($user, $space->building_id);
        $ownTenantIds = $tenant ? LeaseTenant::where('lease_id', $tenant->lease_id)->pluck('id')->all() : [];

        $bookings = Booking::active()
            ->where('shared_space_id', $space->id)
            ->where('starts_at', '>=', $weekStart)
            ->where('starts_at', '<', $weekStart->addWeek())
            ->with('leaseTenant.lease.apartment')
            ->orderBy('starts_at')
            ->get()
            ->map(function (Booking $booking) use ($ownTenantIds, $isLandlord) {
                $own = in_array($booking->lease_tenant_id, $ownTenantIds, true);
                $apartment = $booking->leaseTenant->lease->apartment->label;

                return [
                    'id' => $booking->id,
                    'starts_at' => $booking->starts_at->format('Y-m-d\TH:i'),
                    'ends_at' => $booking->ends_at->format('Y-m-d\TH:i'),
                    'own' => $own,
                    // Najemnik vidi samo »Zasedeno« ali svoje stanovanje, lastnik vidi stanovanje.
                    'apartment' => $own || $isLandlord ? $apartment : null,
                ];
            });

        $usage = null;
        if ($tenant) {
            $used = $this->bookings->usedMinutes($space, $tenant, $weekStart);
            $usage = [
                'used_minutes' => $used,
                'max_minutes' => $space->max_hours_per_week * 60,
                'available_minutes' => max(0, $space->max_hours_per_week * 60 - $used),
                'apartment' => $tenant->lease->apartment->label,
            ];
        }

        return response()->json(['data' => [
            'space' => $this->spaceData($space),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->addDays(6)->toDateString(),
            'bookings' => $bookings,
            'usage' => $usage,
            'can_book' => $tenant !== null,
        ]]);
    }

    /** Nova rezervacija termina (US-08, #116, #117). */
    public function book(Request $request, SharedSpace $space): JsonResponse
    {
        Gate::authorize('view', $space->building);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
        ], [], ['starts_at' => 'začetek', 'ends_at' => 'konec']);

        $tenant = $this->currentTenant($request->user(), $space->building_id);
        abort_if(! $tenant, 403, 'Prostor lahko rezervira samo najemnik z aktivnim najemom.');

        $booking = $this->bookings->book(
            $space,
            $tenant,
            CarbonImmutable::parse($data['starts_at']),
            CarbonImmutable::parse($data['ends_at']),
        );

        return response()->json(['data' => [
            'id' => $booking->id,
            'starts_at' => $booking->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $booking->ends_at->format('Y-m-d\TH:i'),
            'fee_cents' => $booking->fee_cents,
            'charge_period_label' => $booking->fee_cents > 0 ? Dates::monthName(Dates::nextPeriod($booking->starts_at)) : null,
        ]], 201);
    }

    /** Najemnik z aktivnim najemom v stavbi, ki še živi v stanovanju. */
    private function currentTenant(User $user, int $buildingId): ?LeaseTenant
    {
        return LeaseTenant::where('user_id', $user->id)
            ->whereNull('moved_out_on')
            ->whereHas('lease', fn ($q) => $q->whereIn('status', LeaseStatus::occupying())
                ->whereHas('apartment', fn ($q) => $q->where('building_id', $buildingId)))
            ->with('lease.apartment')
            ->first();
    }

    private function spaceData(SharedSpace $space): array
    {
        return [
            'id' => $space->id,
            'name' => $space->name,
            'price_per_hour_cents' => $space->price_per_hour_cents,
            'open_from' => substr($space->open_from, 0, 5),
            'open_to' => substr($space->open_to, 0, 5),
            'max_hours_per_week' => $space->max_hours_per_week,
            'free_cancel_hours' => $space->free_cancel_hours,
        ];
    }
}
