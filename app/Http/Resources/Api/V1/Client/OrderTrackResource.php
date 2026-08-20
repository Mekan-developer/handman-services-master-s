<?php

namespace App\Http\Resources\Api\V1\Client;

use App\Models\MasterLocation;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Everything the client app needs to draw the "master is on the way" map in one
 * response: where he is going, where he has been, and whether it should still
 * be following him at all.
 *
 * @property Order $resource
 */
class OrderTrackResource extends JsonResource
{
    /**
     * @param  Collection<int, MasterLocation>  $points  Trail in chronological order.
     */
    public function __construct(Order $resource, private readonly Collection $points)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $last = $this->points->last();

        return [
            'order_id' => $this->id,
            'status' => $this->status->value,

            // The app polls or listens only while this is true. It flips to
            // false the moment the job is completed or cancelled, which is the
            // signal to tear the map down.
            'is_active' => $this->isTrackingActive(),

            'destination' => [
                'latitude' => (float) $this->client_lat,
                'longitude' => (float) $this->client_lng,
                'address' => $this->client_address,
            ],

            'master' => $this->master === null ? null : [
                'id' => $this->master->id,
                'name' => $this->master->name,
                'phone' => $this->master->phone,
            ],

            'last_location' => $last === null ? null : $this->pointToArray($last, withDistance: true),

            /** @var array<int, array<string, mixed>> */
            'points' => $this->points
                ->map(fn (MasterLocation $point) => $this->pointToArray($point))
                ->all(),
        ];
    }

    private function isTrackingActive(): bool
    {
        return $this->status->isTrackable() && $this->master_id !== null;
    }

    /** @return array<string, mixed> */
    private function pointToArray(MasterLocation $point, bool $withDistance = false): array
    {
        $data = [
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
            'recorded_at' => $point->recorded_at->toIso8601String(),
        ];

        // Only the head of the trail answers "how far away is he now?" — adding
        // it to every historic point would just bloat the response.
        if ($withDistance) {
            $data['distance_km'] = round(
                $this->resource->distanceKmTo((float) $point->latitude, (float) $point->longitude),
                2,
            );
        }

        return $data;
    }
}
