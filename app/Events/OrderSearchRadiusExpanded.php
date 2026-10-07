<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Signals the master apps that the pool of available orders changed. The payload
 * is deliberately just an id and a radius — the channel is public, so anything
 * identifying the client stays behind the authenticated
 * GET /api/v1/master/orders/available endpoint.
 */
class OrderSearchRadiusExpanded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $previousRadiusKm  Radius before this step — masters inside it
     *                                 were already offered the order.
     */
    public function __construct(
        public Order $order,
        public int $previousRadiusKm = 0,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new Channel('available-orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.search.radius.expanded';
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
