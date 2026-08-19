<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Events\OrderSearchStarted;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\SettingRepository;

/**
 * Puts a pending, unassigned order back into the auto-search pool — either
 * because its own search hit the max radius without a taker, or because it
 * was created (e.g. by an admin) without ever entering the pool.
 */
class RestartOrderSearchAction
{
    public function __construct(
        private readonly OrderRepository $repository,
        private readonly SettingRepository $settingRepository,
    ) {}

    public function handle(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending || $order->master_id !== null) {
            throw OrderException::searchRestartNotAllowed();
        }

        $radii = $this->settingRepository->searchRadii();
        $restarted = $this->repository->restartSearch($order, $radii['initial']);

        OrderSearchStarted::dispatch($restarted);

        return $restarted;
    }
}
