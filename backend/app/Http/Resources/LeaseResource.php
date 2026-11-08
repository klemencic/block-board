<?php

namespace App\Http\Resources;

use App\Models\Lease;
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
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
