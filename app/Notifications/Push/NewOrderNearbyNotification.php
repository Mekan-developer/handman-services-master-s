<?php

namespace App\Notifications\Push;

use App\Models\Order;

/** To the master: a new order in one of their categories is within reach. */
class NewOrderNearbyNotification extends PushNotification
{
    public function __construct(public Order $order) {}

    protected function type(): string
    {
        return 'order.nearby';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_MASTER;
    }

    protected function title(): string
    {
        return __('push.master.new_order.title');
    }

    protected function body(): string
    {
        return __('push.master.new_order.body', [
            'category' => $this->order->category?->name,
            'address' => $this->order->client_address ?? $this->order->city?->name,
        ]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return ['order_id' => (string) $this->order->id];
    }
}
