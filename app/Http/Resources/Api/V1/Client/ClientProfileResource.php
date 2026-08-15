<?php

namespace App\Http\Resources\Api\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'city_id' => $this->city_id,
            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name' => $this->city->name,
            ] : null),

            // Master role on this account, so the app can decide between showing
            // "become a master" and opening the master side. `null` status means
            // the client has never applied. Keyed off relationLoaded() rather
            // than whenLoaded(), which collapses a loaded-but-empty relation to
            // null and would hide `has_master_access`.
            'master_status' => $this->when(
                $this->relationLoaded('master'),
                fn () => $this->master?->status->value,
            ),
            'master_id' => $this->when(
                $this->relationLoaded('master'),
                fn () => $this->master?->id,
            ),
            'has_master_access' => $this->when(
                $this->relationLoaded('master'),
                fn () => $this->master !== null
                    && $this->master->isApproved()
                    && $this->master->is_active
                    && $this->master->hasActiveAccess(),
            ),

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
