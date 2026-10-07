<?php

namespace App\Notifications\Push;

use App\Models\Order;

/** To the client: a master was confirmed on their order. */
class MasterAssignedNotification extends PushNotification
{
    public function __construct(public Order $order) {}

    protected function type(): string
    {
        return 'master.assigned';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_CLIENT;
    }

    protected function title(): string
    {
        return __('push.client.master_assigned.title');
    }

    protected function body(): string
    {
        return __('push.client.master_assigned.body', [
            'master' => $this->order->master?->name,
            'order' => $this->order->id,
        ]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return ['order_id' => (string) $this->order->id];
    }
}
