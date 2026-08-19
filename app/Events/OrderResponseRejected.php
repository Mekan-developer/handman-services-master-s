<?php

namespace App\Events;

use App\Models\OrderMasterResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** The client explicitly turned down this master's response, with an optional reason. */
class OrderResponseRejected implements ShouldBroadcastNow
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
        return 'order.response.rejected';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->response->order_id,
            'reason' => $this->response->rejection_reason,
        ];
    }
}
