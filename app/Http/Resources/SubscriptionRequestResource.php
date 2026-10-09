<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $master = $this->client?->master;

        return [
            'id' => $this->id,
            'client' => $this->client === null ? null : [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'phone' => $this->client->phone,
            ],
            // Approval needs an approved master profile — the page warns up front
            // instead of letting the administrator find out on submit.
            'master_status' => $master?->status->value,
            'master_status_label' => $master?->status->label(),
            'can_be_approved' => $master !== null && $master->isApproved(),
            'has_active_access' => $master?->hasActiveAccess() ?? false,
            'access_expires_at' => $master?->access_expires_at?->format('d.m.Y'),
            'plan' => $this->plan === null ? null : [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'price' => (float) $this->plan->price,
                'duration_days' => $this->plan->duration_days,
                'is_available' => $this->plan->is_active && ! $this->plan->trashed(),
            ],
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_pending' => $this->isPending(),
            'rejection_reason' => $this->rejection_reason,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'reviewed_at' => $this->reviewed_at?->format('d.m.Y H:i'),
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription === null ? null : [
                'id' => $this->subscription->id,
                'price_paid' => (float) $this->subscription->price_paid,
                'starts_at' => $this->subscription->starts_at?->format('d.m.Y'),
                'expires_at' => $this->subscription->expires_at?->format('d.m.Y'),
            ]),
            'created_at' => $this->created_at->format('d.m.Y H:i'),
        ];
    }
}
