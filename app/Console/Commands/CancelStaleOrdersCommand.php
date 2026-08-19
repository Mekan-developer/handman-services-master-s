<?php

namespace App\Console\Commands;

use App\Actions\CancelStaleOrdersAction;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\SettingRepository;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CancelStaleOrdersCommand extends Command
{
    protected $signature = 'orders:cancel-stale-orders';

    protected $description = 'Cancel pending orders that stayed unassigned past the configured response deadline';

    public function handle(
        OrderRepository $orderRepository,
        SettingRepository $settingRepository,
        CancelStaleOrdersAction $action,
    ): int {
        $hours = $settingRepository->orderAutoCancelHours();
        $cancelled = 0;

        $orderRepository->eachStaleUnassigned(function (Collection $orders) use ($action, &$cancelled): void {
            $orders->each(fn (Order $order) => $action->handle($order));

            $cancelled += $orders->count();
        }, $hours);

        $this->info("Cancelled {$cancelled} stale order(s).");

        return self::SUCCESS;
    }
}
