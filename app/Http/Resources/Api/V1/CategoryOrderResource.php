<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * An open order in one of the master's categories, possibly outside reach.
 *
 * Same shape as AvailableOrderResource, except the distance is unknown until
 * the master has sent a GPS ping, and `is_within_radius` tells the app whether
 * the respond button should be enabled.
 */
class CategoryOrderResource extends AvailableOrderResource
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
            'distance_km' => $this->distance_km !== null ? (float) $this->distance_km : null,
            'is_within_radius' => (bool) $this->is_within_radius,
        ];
    }
}
