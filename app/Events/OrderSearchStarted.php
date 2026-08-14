<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new order entered the auto-search pool.
 *
 * Sibling of {@see OrderSearchRadiusExpanded}: both are contentless signals on
 * the public `available-orders` channel telling master apps to re-fetch
 * GET /api/v1/master/orders/available, which is where the matching rules and
 * the client's details live.
 */
class OrderSearchStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new Channel('available-orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.search.started';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'radius_km' => $this->order->search_radius_km,
        ];
    }
}
