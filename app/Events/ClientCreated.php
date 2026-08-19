<?php

namespace App\Events;

use App\Models\Client;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Client $client) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        // Public, like `orders` — the phone number stays off this channel since
        // anyone could subscribe; admins get it from the database notification
        // instead, which is only readable through an authenticated request.
        return [
            new Channel('clients'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'client.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->client->id,
            'name' => $this->client->name,
            'city' => $this->client->city?->name,
        ];
    }
}
