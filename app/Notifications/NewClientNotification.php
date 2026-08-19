<?php

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewClientNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Client $client) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_client',
            'client_id' => $this->client->id,
            'client_name' => $this->client->name,
            'phone' => $this->client->phone,
            'city' => $this->client->city?->name,
        ];
    }
}
