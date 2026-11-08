<?php

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Exceptions\WorkflowException;
use App\Models\Apartment;
use App\Models\Lease;
use App\Models\LeaseTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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
