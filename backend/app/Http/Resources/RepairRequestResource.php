<?php

namespace App\Http\Resources;

use App\Models\RepairEvent;
use App\Models\RepairRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RepairRequest
 */
class RepairRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isLandlord = $user->isLandlordOf($this->building_id);
        $isContractor = $this->contractor_id === $user->id && ! $isLandlord;

        return [
            'id' => $this->id,
            'number' => $this->number(),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'payer' => $this->payer->value,
            'payer_label' => $this->payer->label(),
            'default_payer' => $this->default_payer->value,
            'location' => [
                'label' => $this->locationLabel(),
                'apartment' => $this->apartment ? ['id' => $this->apartment->id, 'label' => $this->apartment->label, 'floor' => $this->apartment->floor] : null,
                'shared_space' => $this->sharedSpace ? ['id' => $this->sharedSpace->id, 'name' => $this->sharedSpace->name] : null,
            ],
            'building' => [
                'name' => $this->building->name,
                'address' => $this->building->address,
            ],
            // Izvajalec vidi samo ime in telefon prijavitelja (US-11).
            'reporter' => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
                'phone' => $isLandlord || $isContractor ? $this->reporter->phone : null,
            ],
            'contractor' => $this->contractor ? [
                'id' => $this->contractor->id,
                'name' => $this->contractor->name,
                'phone' => $this->contractor->phone,
            ] : null,
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d\TH:i'),
            'cost_cents' => $this->cost_cents,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'photos' => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'context' => $a->context,
                'url' => '/api/attachments/'.$a->id,
            ]),
            'timeline' => $this->whenLoaded('events', fn () => $this->events->map(fn (RepairEvent $event) => [
                'id' => $event->id,
                'kind' => $event->kind,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'note' => $event->note,
                'meta' => $event->meta,
                'user' => $event->user?->name ?? 'Sistem',
                'created_at' => $event->created_at,
            ])),
            'permissions' => [
                'manage' => $isLandlord,
                'perform' => $this->contractor_id === $user->id,
            ],
        ];
    }
}
