<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Notifications\Push\OrderCancelledNotification;
use App\Notifications\Push\OrderStatusChangedNotification;

/**
 * The client hears when the master sets off / starts and when the job is done;
 * the assigned master hears when the order is cancelled under them. Masters
 * who only responded are told by {@see NotifyMasterAboutWithdrawnResponse}.
 */
class NotifyAboutOrderStatusChange
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        match ($event->to) {
            OrderStatus::InProgress, OrderStatus::Completed => $order->client
                ?->notify(new OrderStatusChangedNotification($order, $event->to)),
            OrderStatus::Cancelled => $order->master?->notify(new OrderCancelledNotification($order)),
            default => null,
        };
    }
}
