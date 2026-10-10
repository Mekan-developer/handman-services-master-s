<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Master;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\SettingRepository;
use Illuminate\Support\Facades\DB;

/**
 * A master takes a decline back, so the order shows up in their feeds again.
 *
 * The restore window is counted from the moment of the decline (its
 * `created_at`) and is inclusive. No push is re-sent — the master is already
 * looking at the order.
 */
class RestoreDeclinedOrderAction
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly SettingRepository $settings,
    ) {}

    /** @throws OrderException */
    public function handle(Master $master, Order $order): void
    {
        $windowMinutes = $this->settings->orderDeclineRestoreMinutes();

        DB::transaction(function () use ($master, $order, $windowMinutes): void {
            // Locked: another master may be claiming this very order right now.
            $locked = $this->orders->lockForUpdate($order);

            $decline = $this->orders->findDeclineForMaster($locked, $master->id);

            if ($decline === null) {
                throw OrderException::notDeclined();
            }

            if ($locked->master_id !== null) {
                throw OrderException::alreadyClaimed();
            }

            if ($locked->status !== OrderStatus::Pending) {
                throw OrderException::noLongerAvailable();
            }

            if (now()->greaterThan($decline->created_at->copy()->addMinutes($windowMinutes))) {
                throw OrderException::restoreWindowExpired();
            }

            $this->orders->restoreDeclineForMaster($decline);
        });
    }
}
