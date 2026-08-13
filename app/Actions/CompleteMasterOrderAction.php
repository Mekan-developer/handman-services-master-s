<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Master;
use App\Models\Order;

class CompleteMasterOrderAction
{
    public function __construct(private readonly UpdateOrderStatusAction $updateStatus) {}

    public function handle(Master $master, Order $order): Order
    {
        if ($order->master_id !== $master->id) {
            throw OrderException::invalidTransition($order->status->value, OrderStatus::Completed->value);
        }

        return $this->updateStatus->handle($order, OrderStatus::Completed);
    }
}
