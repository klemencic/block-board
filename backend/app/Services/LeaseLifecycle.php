<?php

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Enums\Role;
use App\Exceptions\WorkflowException;
use App\Models\Apartment;
use App\Models\Lease;
use App\Models\LeaseTenant;
use App\Models\User;
use App\Notifications\LeaseNoticeGiven;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Dovoljeni prehodi med stanji najema (ADR-0006, ADR-0007).
 */
class LeaseLifecycle
{
    public function __construct(private readonly TenantInviter $inviter) {}

    /**
     * Nov najem v stanju osnutek. Osnutek ne ustvarja obveznosti.
     *
     * @param  array{starts_on: string, ends_on?: string|null, notice_period_days: int, monthly_rent_cents: int,
     *     deposit_cents: int, tenants: list<array{name: string, email: string, phone?: string|null, rent_share_cents: int}>}  $data
     */
    public function create(Apartment $apartment, array $data): Lease
    {
        $notifications = [];

        $lease = DB::transaction(function () use ($apartment, $data, &$notifications) {
            $lease = Lease::create([
                'apartment_id' => $apartment->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'notice_period_days' => $data['notice_period_days'],
                'monthly_rent_cents' => $data['monthly_rent_cents'],
                'deposit_cents' => $data['deposit_cents'],
                'status' => LeaseStatus::Draft,
            ]);

            foreach ($data['tenants'] as $tenant) {
                $user = $this->inviter->findOrCreate($tenant);
                $this->inviter->grantAccess($user, $apartment->building);

                LeaseTenant::create([
                    'lease_id' => $lease->id,
                    'user_id' => $user->id,
                    'rent_share_cents' => $tenant['rent_share_cents'],
                    'moved_in_on' => $data['starts_on'],
                ]);

                $notifications[] = [$user, $user->wasRecentlyCreated];
            }

            return $lease;
        });

        foreach ($notifications as [$user, $isNew]) {
            $this->inviter->notify($user, $lease, $isNew);
        }

        return $lease->load('tenants.user', 'apartment');
    }

    /**
     * Osnutek postane aktiven, če stanovanje nima drugega aktivnega najema.
     */
    public function activate(Lease $lease): Lease
    {
        if ($lease->status !== LeaseStatus::Draft) {
            throw new WorkflowException('Aktivirati je mogoče samo najem v stanju osnutek.');
        }

        try {
            DB::transaction(function () use ($lease) {
                // Zaklep stanovanja prepreči sočasno aktivacijo dveh najemov.
                Apartment::whereKey($lease->apartment_id)->lockForUpdate()->first();
                $this->ensureApartmentIsFree($lease);

                $lease->update(['status' => LeaseStatus::Active]);
            });
        } catch (UniqueConstraintViolationException) {
            // Delni enolični indeks v bazi je zadnja varovalka.
            $this->ensureApartmentIsFree($lease);
            throw new WorkflowException('Stanovanje že ima aktiven najem.');
        }

        return $lease->refresh();
    }

    /**
     * Datum prenehanja = datum odpovedi + odpovedni rok v dneh (US-02, #98).
     * Pri najemu za določen čas datum prenehanja ni nikoli po datumu konca.
     */
    public function terminationDate(Lease $lease, CarbonInterface $noticeDate): CarbonImmutable
    {
        $date = CarbonImmutable::parse($noticeDate->toDateString())->addDays($lease->notice_period_days);

        if ($lease->ends_on && $date->greaterThan($lease->ends_on)) {
            return CarbonImmutable::parse($lease->ends_on->toDateString());
        }

        return $date;
    }

    /**
     * Najemnik potrdi odpoved. Ko jo potrdijo vsi sostanovalci, najem preide
     * v odpovedni rok; datum odpovedi je dan zadnje potrditve (US-02, #97, #99).
     */
    public function confirmNotice(Lease $lease, User $user): Lease
    {
        $becameInNotice = DB::transaction(function () use ($lease, $user) {
            $lease = Lease::whereKey($lease->id)->lockForUpdate()->firstOrFail();

            if ($lease->status !== LeaseStatus::Active) {
                throw new WorkflowException('Odpoved je mogoča samo pri aktivnem najemu.');
            }

            $tenant = $lease->currentTenants()->where('user_id', $user->id)->first();
            if (! $tenant) {
                throw new WorkflowException('Na tem najemu niste naveden kot najemnik.');
            }

            if (! $tenant->notice_confirmed_at) {
                $tenant->update(['notice_confirmed_at' => now()]);
            }

            if ($lease->currentTenants()->whereNull('notice_confirmed_at')->exists()) {
                return false;
            }

            $today = today();
            $lease->update([
                'status' => LeaseStatus::InNotice,
                'notice_given_on' => $today,
                'terminates_on' => $this->terminationDate($lease, $today),
            ]);

            return true;
        });

        $lease->refresh();

        if ($becameInNotice) {
            $this->notifyNoticeGiven($lease);
        }

        return $lease;
    }

    /**
     * Zaključi najeme, ki jim je danes ali prej potekel odpovedni rok ali datum konca (US-02, #99).
     *
     * @return int število zaključenih najemov
     */
    public function endDueLeases(?CarbonInterface $today = null): int
    {
        $today = ($today ?? today())->toDateString();

        $due = Lease::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('status', LeaseStatus::InNotice)->whereDate('terminates_on', '<=', $today))
                ->orWhere(fn ($q) => $q->where('status', LeaseStatus::Active)->whereNotNull('ends_on')->whereDate('ends_on', '<=', $today)))
            ->get();

        foreach ($due as $lease) {
            DB::transaction(function () use ($lease) {
                $endDate = $lease->terminates_on ?? $lease->ends_on;
                $lease->update(['status' => LeaseStatus::Ended, 'terminates_on' => $endDate]);
                $lease->tenants()->whereNull('moved_out_on')->update(['moved_out_on' => $endDate]);
            });
        }

        return $due->count();
    }

    private function notifyNoticeGiven(Lease $lease): void
    {
        $lease->load('apartment.building', 'tenants.user');

        $landlords = User::whereHas('memberships', fn ($q) => $q
            ->where('building_id', $lease->apartment->building_id)
            ->where('role', Role::Landlord))->get();

        Notification::send(
            $landlords->merge($lease->tenants->map->user)->unique('id'),
            new LeaseNoticeGiven($lease)
        );
    }

    private function ensureApartmentIsFree(Lease $lease): void
    {
        $existing = Lease::occupying()
            ->where('apartment_id', $lease->apartment_id)
            ->whereKeyNot($lease->id)
            ->first();

        if ($existing) {
            throw new WorkflowException('Stanovanje že ima aktiven najem.', [
                'conflicting_lease_id' => $existing->id,
            ]);
        }
    }
}
