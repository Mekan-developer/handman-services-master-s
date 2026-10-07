<?php

namespace App\Notifications\Push;

use App\Models\Order;

/** To the master: the client accepted their response, the order is theirs. */
class ResponseApprovedNotification extends PushNotification
{
    public function __construct(public Order $order) {}

    protected function type(): string
    {
        return 'order.response.approved';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_MASTER;
    }

    protected function title(): string
    {
        return __('push.master.response_approved.title');
    }

    protected function body(): string
    {
        return __('push.master.response_approved.body', ['order' => $this->order->id]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return ['order_id' => (string) $this->order->id];
    }
}
