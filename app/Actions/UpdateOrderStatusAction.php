<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Repositories\OrderRepository;

class UpdateOrderStatusAction
{
    public function __construct(private readonly OrderRepository $repository) {}

    public function handle(Order $order, OrderStatus $newStatus, ?string $cancelReason = null): Order
    {
        if ($order->status->isFinal()) {
            throw OrderException::alreadyFinal();
        }

        if (! $this->isValidTransition($order->status, $newStatus)) {
            throw OrderException::invalidTransition($order->status->value, $newStatus->value);
        }

        $previousStatus = $order->status;
        $updated = $this->repository->changeStatus($order, $newStatus);

        if ($newStatus === OrderStatus::Cancelled && $cancelReason !== null) {
            $updated->update(['cancel_reason' => $cancelReason]);
        }

        OrderStatusChanged::dispatch($updated, $previousStatus, $newStatus);

        return $updated->fresh();
    }

    private function isValidTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return match ($from) {
            // Pending -> Assigned only happens via AssignMasterAction, which also sets master_id/assigned_at.
            OrderStatus::Pending => $to === OrderStatus::Cancelled,
            OrderStatus::Assigned => in_array($to, [OrderStatus::InProgress, OrderStatus::Cancelled], true),
            OrderStatus::InProgress => in_array($to, [OrderStatus::Completed, OrderStatus::Cancelled], true),
            default => false,
        };
    }
}
