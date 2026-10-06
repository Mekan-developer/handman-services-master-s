<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An unclaimed order offered to a master by the auto-search.
 *
 * Narrower than MasterOrderResource: the order does not belong to this master
 * yet. The client's name and phone, location and description are exposed so the
 * master can decide whether to respond.
 */
class AvailableOrderResource extends JsonResource
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
            'client_name' => $this->client_name,
            'client_phone' => $this->client_phone,
            'category' => $this->whenLoaded('category', fn () => $this->category->name),
            'city' => $this->whenLoaded('city', fn () => $this->city->name),
            'description' => $this->description,
            'address' => $this->client_address,
            'latitude' => $this->client_lat ? (float) $this->client_lat : null,
            'longitude' => $this->client_lng ? (float) $this->client_lng : null,
            'distance_km' => (float) $this->distance_km,
            'search_radius_km' => $this->search_radius_km,
            'search_started_at' => $this->search_started_at?->toIso8601String(),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($p) => [
                'id' => $p->id,
                'url' => $p->path ? asset('storage/'.$p->path) : null,
                'status' => $p->status,
            ])),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
