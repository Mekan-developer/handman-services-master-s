<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;

/** An order nobody claimed within the configured response deadline gives up automatically. */
class CancelStaleOrdersAction
{
    public function __construct(private readonly UpdateOrderStatusAction $updateStatus) {}

    public function handle(Order $order): Order
    {
        return $this->updateStatus->handle($order, OrderStatus::Cancelled, (string) __('orders.notifications.auto_cancelled_reason'));
    }
}
