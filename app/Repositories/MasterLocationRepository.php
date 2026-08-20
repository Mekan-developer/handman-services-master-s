<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class MasterLocationRepository
{
    /**
     * Newest pings kept when a client asks for the whole trail. A job lasting
     * hours at a 10-second ping rate would otherwise ship tens of thousands of
     * points into a phone that only draws a polyline.
     */
    private const TRACK_POINT_LIMIT = 500;

    /** @param array{latitude: float, longitude: float, order_id?: int|null, recorded_at?: string|null} $data */
    public function create(Master $master, array $data): MasterLocation
    {
        return $master->locations()->create([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'order_id' => $data['order_id'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);
    }

    /**
     * The trail recorded while working on one order, oldest point first — the
     * polyline the client app draws.
     *
     * Reading is capped at the newest {@see self::TRACK_POINT_LIMIT} pings and
     * then flipped back into chronological order, so a long job loses the head
     * of the trail rather than the part the client is actually watching.
     *
     * @param  CarbonInterface|null  $since  Only pings recorded after this moment —
     *                                       lets a reconnecting app fetch the tail
     *                                       instead of the whole trail again.
     * @return Collection<int, MasterLocation>
     */
    public function trackForOrder(Order $order, ?CarbonInterface $since = null, int $limit = self::TRACK_POINT_LIMIT): Collection
    {
        return $order->masterLocations()
            ->when($since, fn ($query) => $query->where('recorded_at', '>', $since))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'master_id', 'order_id', 'latitude', 'longitude', 'recorded_at'])
            ->reverse()
            ->values();
    }

    /**
     * Delete pings nobody needs any more.
     *
     * Two clocks, because the two kinds of ping are worth keeping for different
     * reasons. A ping tagged with a finished order is evidence — it survives
     * `$closedOrderCutoff` past the moment that job closed, long enough to
     * settle a "he never showed up" dispute. An untagged ping is just a master
     * moving around town: it exists to place a dot on the admin map, and a
     * week-old dot is worth nothing.
     *
     * The newest ping of every master is never deleted — it is what keeps them
     * on the map at all, however long they have been off the air.
     *
     * Deletes in chunks of ids rather than one big `DELETE ... LIMIT`, which
     * SQLite refuses unless compiled for it, and which would hold a lock over
     * millions of rows on MySQL.
     *
     * @return int Rows removed.
     */
    public function prune(CarbonInterface $pingCutoff, CarbonInterface $closedOrderCutoff, int $chunkSize = 1000): int
    {
        $keepIds = MasterLocation::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('master_id')
            ->pluck('id')
            ->all();

        $deleted = 0;

        do {
            $ids = MasterLocation::query()
                ->where(function ($query) use ($pingCutoff, $closedOrderCutoff) {
                    $query
                        ->where(fn ($idle) => $idle
                            ->whereNull('order_id')
                            ->where('recorded_at', '<', $pingCutoff))
                        ->orWhereHas('order', fn ($order) => $order
                            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
                            ->where(fn ($closed) => $closed
                                ->where('completed_at', '<', $closedOrderCutoff)
                                ->orWhere('cancelled_at', '<', $closedOrderCutoff)));
                })
                ->whereNotIn('id', $keepIds)
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += MasterLocation::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunkSize);

        return $deleted;
    }

    /** Where the master was on their last ping for this order, or null if they never reported. */
    public function latestForOrder(Order $order): ?MasterLocation
    {
        return $order->masterLocations()
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }
}
