<?php

namespace App\Events;

use App\Models\OrderMasterResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** The order itself was cancelled (by the client or the stale-order sweep) while this response was still pending. */
class OrderResponseWithdrawn implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OrderMasterResponse $response) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('master.'.$this->response->master_id)];
    }

    public function broadcastAs(): string
    {
        return 'order.response.withdrawn';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->response->order_id,
        ];
    }
}
