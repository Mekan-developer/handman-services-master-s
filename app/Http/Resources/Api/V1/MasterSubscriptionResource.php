<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterSubscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_name' => $this->plan_name,
            'price_paid' => (float) $this->price_paid,
            'duration_days' => $this->duration_days,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'starts_at' => $this->starts_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'days_left' => $this->expires_at !== null && $this->expires_at->isFuture()
                ? now()->diffInDays($this->expires_at, false)
                : 0,
        ];
    }
}
