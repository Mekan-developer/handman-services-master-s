<?php

namespace App\Actions;

use App\Actions\Concerns\EnsuresMasterEligibility;
use App\Events\MasterAssigned;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Repositories\MasterRepository;
use App\Repositories\OrderRepository;

class AssignMasterAction
{
    use EnsuresMasterEligibility;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly MasterRepository $masterRepository,
    ) {}

    public function handle(Order $order, int $masterId, ?string $changeReason = null): Order
    {
        if ($order->status->isFinal()) {
            throw OrderException::alreadyFinal();
        }

        $master = $this->masterRepository->findOrFail($masterId);

        $this->ensureMasterCanTakeOrders($master);

        // Manual assignment stays city-bound; only the geo auto-search crosses
        // city borders, and it never routes through this action.
        if ($master->city_id !== $order->city_id) {
            throw OrderException::cityMismatch();
        }

        if (! in_array($order->category_id, $this->orderRepository->masterCategoryIds($master), true)) {
            throw OrderException::categoryMismatch();
        }

        $isReassignment = $order->master_id !== null && $order->master_id !== $master->id;
        $assigned = $this->orderRepository->assignMaster($order, $master->id, $isReassignment ? $changeReason : null);

        MasterAssigned::dispatch($assigned->load('master'));

        return $assigned;
    }
}
