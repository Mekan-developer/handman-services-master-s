<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * An order the master declined and may still take back.
 *
 * Same shape as AvailableOrderResource; the distance is not computed here, and
 * `restore_until` tells the app until when the "return" button stays enabled.
 */
class DeclinedOrderResource extends AvailableOrderResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'distance_km' => null,
            'declined_at' => $this->declined_at?->toIso8601String(),
            'restore_until' => $this->restore_until?->toIso8601String(),
        ];
    }
}
