<?php

namespace App\Actions;

use App\Events\OrderSearchExhausted;
use App\Events\OrderSearchRadiusExpanded;
use App\Models\Order;
use App\Repositories\OrderRepository;

/**
 * Grows one order's search radius to match how long it has been waiting.
 *
 * radius(n) = n * initial, where n is the minute of the search the order is
 * currently in — minute 1 keeps the initial radius, minute 2 doubles it, and so
 * on. Once the next step would overshoot the configured maximum the search is
 * closed: the radius is pinned at the maximum (masters there can still respond)
 * and administrators are notified.
 *
 * Derived from elapsed time rather than incremented per tick, so a missed or
 * duplicated scheduler run cannot drift the radius.
 */
class ExpandOrderSearchRadiusAction
{
    public function __construct(private readonly OrderRepository $repository) {}

    public function handle(Order $order, int $initialRadiusKm, int $maxRadiusKm): void
    {
        if ($order->search_started_at === null || $order->search_expired_at !== null) {
            return;
        }

        $elapsedSeconds = max(0, now()->getTimestamp() - $order->search_started_at->getTimestamp());
        $minuteNumber = intdiv($elapsedSeconds, 60) + 1;
        $newRadiusKm = $minuteNumber * $initialRadiusKm;

        if ($newRadiusKm > $maxRadiusKm) {
            OrderSearchExhausted::dispatch($this->repository->markSearchExpired($order, $maxRadiusKm));

            return;
        }

        if ($newRadiusKm === $order->search_radius_km) {
            return;
        }

        OrderSearchRadiusExpanded::dispatch($this->repository->expandRadius($order, $newRadiusKm));
    }
}
