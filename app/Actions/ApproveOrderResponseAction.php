<?php

namespace App\Actions;

use App\Enums\OrderResponseStatus;
use App\Enums\OrderStatus;
use App\Events\MasterAssigned;
use App\Events\OrderResponseSuperseded;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use App\Repositories\OrderRepository;

/**
 * The client picks a winner among the masters who responded. Every other
 * still-pending response on the order is superseded, not rejected — those
 * masters lost the order to someone else, not to an explicit client decision.
 */
class ApproveOrderResponseAction
{
    public function __construct(private readonly OrderRepository $orderRepository) {}

    /** @throws OrderException */
    public function handle(Order $order, OrderMasterResponse $response): Order
    {
        if ($order->status !== OrderStatus::Pending || $order->master_id !== null) {
            throw OrderException::alreadyClaimed();
        }

        if ($response->order_id !== $order->id || $response->status !== OrderResponseStatus::Pending) {
            throw OrderException::responseNotPending();
        }

        [$assigned, $superseded] = $this->orderRepository->approveResponse($order, $response);

        MasterAssigned::dispatch($assigned->load('master'));

        $superseded->each(fn (OrderMasterResponse $r) => OrderResponseSuperseded::dispatch($r));

        return $assigned;
    }
}
