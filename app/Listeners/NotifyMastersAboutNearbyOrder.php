<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderSearchRadiusExpanded;
use App\Events\OrderSearchStarted;
use App\Notifications\Push\NewOrderNearbyNotification;
use App\Repositories\MasterRepository;
use App\Repositories\OrderRepository;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Pushes a fresh order to the masters the auto-search has just reached: everyone
 * inside the starting radius when the search begins, then on every expansion
 * only those in the newly added ring — so nobody is told twice.
 *
 * Queued after commit: the search starts inside the order-creation transaction,
 * and the distance sweep over masters has no business in the API response time.
 */
class NotifyMastersAboutNearbyOrder implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    public function __construct(
        private readonly MasterRepository $masters,
        private readonly OrderRepository $orders,
    ) {}

    public function handle(OrderSearchStarted|OrderSearchRadiusExpanded $event): void
    {
        $order = $this->orders->findOrFail($event->order->id);

        // The worker may run a little late — by then the order could be taken.
        if ($order->status !== OrderStatus::Pending || $order->master_id !== null) {
            return;
        }

        $excludeWithinKm = $event instanceof OrderSearchRadiusExpanded ? $event->previousRadiusKm : 0;

        $recipients = $this->masters->reachedBySearch($order, $excludeWithinKm);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new NewOrderNearbyNotification($order->loadMissing(['category', 'city'])));
    }
}
