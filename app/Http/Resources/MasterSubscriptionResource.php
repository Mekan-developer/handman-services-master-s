<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterSubscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'master_id' => $this->master_id,
            'master' => $this->whenLoaded('master', fn () => [
                'id' => $this->master->id,
                'name' => $this->master->name,
                'phone' => $this->master->phone,
            ]),
            'subscription_plan_id' => $this->subscription_plan_id,
            // Snapshot fields — what was actually sold, regardless of later plan edits.
            'plan_name' => $this->plan_name,
            'price_paid' => (float) $this->price_paid,
            'duration_days' => $this->duration_days,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_final' => $this->status->isFinal(),
            'starts_at' => $this->starts_at?->format('d.m.Y'),
            'expires_at' => $this->expires_at?->format('d.m.Y'),
            'days_left' => $this->expires_at !== null && $this->expires_at->isFuture()
                ? now()->diffInDays($this->expires_at, false)
                : 0,
            'note' => $this->note,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->created_at->format('d.m.Y H:i'),
        ];
    }
}
