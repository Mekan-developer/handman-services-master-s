<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One master's response to an order. Used both for the client's list of
 * candidates to pick from, and as the confirmation payload handed back to the
 * responding master.
 */
class OrderMasterResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'master' => $this->whenLoaded('master', fn () => [
                'id' => $this->master->id,
                'name' => $this->master->name,
                'phone' => $this->master->phone,
                'experience_years' => $this->master->experience_years,
                'distance_km' => $this->distance_km !== null ? (float) $this->distance_km : null,
            ]),
        ];
    }
}
