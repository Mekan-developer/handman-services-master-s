<?php

namespace App\Http\Resources;

use App\Models\MasterLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A master's GPS trail, oldest point first — the polyline an admin map draws.
 */
class MasterTrajectoryResource extends ResourceCollection
{
    /** The map reads `points` off the response root; no `data` envelope. */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'points' => $this->collection
                ->map(fn (MasterLocation $point) => [
                    'latitude' => (float) $point->latitude,
                    'longitude' => (float) $point->longitude,
                    'recorded_at' => $point->recorded_at->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
