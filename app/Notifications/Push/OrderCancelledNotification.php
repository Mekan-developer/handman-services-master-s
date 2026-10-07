<?php

namespace App\Notifications\Push;

use App\Models\Order;

/** To the master: an order they responded to or were assigned was cancelled. */
class OrderCancelledNotification extends PushNotification
{
    public function __construct(public Order $order) {}

    protected function type(): string
    {
        return 'order.cancelled';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_MASTER;
    }

    protected function title(): string
    {
        return __('push.master.order_cancelled.title');
    }

    protected function body(): string
    {
        return __('push.master.order_cancelled.body', ['order' => $this->order->id]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return ['order_id' => (string) $this->order->id];
    }
}
