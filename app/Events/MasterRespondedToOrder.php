<?php

namespace App\Events;

use App\Models\OrderMasterResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A master responded to an order still in auto-search. Broadcast to the
 * client so their app can show the new candidate without polling.
 */
class MasterRespondedToOrder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OrderMasterResponse $response) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->response->order->client_id) {
            $channels[] = new PrivateChannel('client.'.$this->response->order->client_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.response.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->response->order_id,
            'response_id' => $this->response->id,
            'master_id' => $this->response->master_id,
            'master_name' => $this->response->master->name,
            'master_phone' => $this->response->master->phone,
        ];
    }
}
