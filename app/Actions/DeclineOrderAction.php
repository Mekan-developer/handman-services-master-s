<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Master;
use App\Models\Order;
use App\Repositories\OrderRepository;

/**
 * A master dismisses an offer. This only hides the order from that master's own
 * feed — the auto-search keeps running and other masters still see it.
 */
class DeclineOrderAction
{
    public function __construct(private readonly OrderRepository $repository) {}

    /** @throws OrderException */
    public function handle(Master $master, Order $order): void
    {
        if ($order->status !== OrderStatus::Pending || $order->master_id !== null) {
            throw OrderException::alreadyClaimed();
        }

        $this->repository->declineForMaster($order, $master->id);
    }
}
