<?php

namespace App\Http\Resources;

use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Services\LeaseLifecycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lease
 */
class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'notice_period_days' => $this->notice_period_days,
            'monthly_rent_cents' => $this->monthly_rent_cents,
            'deposit_cents' => $this->deposit_cents,
            'notice_given_on' => $this->notice_given_on?->toDateString(),
            'terminates_on' => $this->terminates_on?->toDateString(),
            // Predogled: datum prenehanja, če bi odpoved začela veljati danes (US-02, #98).
            'notice_preview_terminates_on' => $this->status === LeaseStatus::Active
                ? app(LeaseLifecycle::class)->terminationDate($this->resource, today())->toDateString()
                : null,
            'building' => $this->whenLoaded('apartment', fn () => $this->apartment->relationLoaded('building') ? [
                'id' => $this->apartment->building->id,
                'name' => $this->apartment->building->name,
                'address' => $this->apartment->building->address,
            ] : null),
            'apartment' => $this->whenLoaded('apartment', fn () => [
                'id' => $this->apartment->id,
                'label' => $this->apartment->label,
                'floor' => $this->apartment->floor,
                'building_id' => $this->apartment->building_id,
            ]),
            'tenants' => $this->whenLoaded('tenants', fn () => $this->tenants->map(fn ($tenant) => [
                'id' => $tenant->id,
                'user_id' => $tenant->user_id,
                'name' => $tenant->user->name,
                'email' => $tenant->user->email,
                'phone' => $tenant->user->phone,
                'rent_share_cents' => $tenant->rent_share_cents,
                'moved_in_on' => $tenant->moved_in_on->toDateString(),
                'moved_out_on' => $tenant->moved_out_on?->toDateString(),
                'notice_confirmed_at' => $tenant->notice_confirmed_at,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
