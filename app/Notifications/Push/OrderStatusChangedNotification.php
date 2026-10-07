<?php

namespace App\Notifications\Push;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * To the client: the master moved their order forward — set off / started the
 * job (`in_progress`) or finished it (`completed`).
 */
class OrderStatusChangedNotification extends PushNotification
{
    public function __construct(
        public Order $order,
        public OrderStatus $status,
    ) {}

    protected function type(): string
    {
        return 'order.status.changed';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_CLIENT;
    }

    protected function title(): string
    {
        return __("push.client.order_{$this->status->value}.title");
    }

    protected function body(): string
    {
        return __("push.client.order_{$this->status->value}.body", [
            'master' => $this->order->master?->name,
            'order' => $this->order->id,
        ]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return [
            'order_id' => (string) $this->order->id,
            'status' => $this->status->value,
        ];
    }
}
