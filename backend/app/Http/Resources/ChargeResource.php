<?php

namespace App\Http\Resources;

use App\Models\Charge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Charge
 */
class ChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenant = $this->relationLoaded('leaseTenant') ? $this->leaseTenant : null;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'period' => $this->period->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'paid_at' => $this->paid_at?->toDateString(),
            // Stanje je izračunano na strežniku, da je enako na seznamu in v podrobnostih (US-04, #104).
            'status' => $this->status(),
            'days_overdue' => $this->daysOverdue(),
            'has_source' => $this->hasSource(),
            'tenant' => $tenant?->relationLoaded('user') ? [
                'id' => $tenant->id,
                'name' => $tenant->user->name,
            ] : null,
            'apartment' => $tenant?->relationLoaded('lease') && $tenant->lease->relationLoaded('apartment') ? [
                'id' => $tenant->lease->apartment->id,
                'label' => $tenant->lease->apartment->label,
            ] : null,
        ];
    }
}
