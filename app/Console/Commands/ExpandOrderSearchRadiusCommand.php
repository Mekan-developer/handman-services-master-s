<?php

namespace App\Console\Commands;

use App\Actions\ExpandOrderSearchRadiusAction;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\SettingRepository;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ExpandOrderSearchRadiusCommand extends Command
{
    protected $signature = 'orders:expand-search-radius';

    protected $description = 'Grow the master search radius of every pending order, closing searches that hit the configured maximum';

    public function handle(
        OrderRepository $orderRepository,
        SettingRepository $settingRepository,
        ExpandOrderSearchRadiusAction $action,
    ): int {
        $radii = $settingRepository->searchRadii();
        $processed = 0;

        $orderRepository->eachPendingSearchable(function (Collection $orders) use ($action, $radii, &$processed): void {
            $orders->each(fn (Order $order) => $action->handle($order, $radii['initial'], $radii['max']));

            $processed += $orders->count();
        });

        $this->info("Processed {$processed} searching order(s).");

        return self::SUCCESS;
    }
}
