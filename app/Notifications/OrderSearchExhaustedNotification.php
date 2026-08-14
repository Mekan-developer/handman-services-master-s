<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderSearchExhaustedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'order_search_exhausted',
            'order_id' => $this->order->id,
            'client_name' => $this->order->client_name,
            'category' => $this->order->category?->name,
            'city' => $this->order->city?->name,
            'address' => $this->order->client_address,
            'search_radius_km' => $this->order->search_radius_km,
        ];
    }
}
