<?php

namespace App\Console\Commands;

use App\Repositories\MasterLocationRepository;
use Illuminate\Console\Command;

/**
 * Keeps `master_locations` from growing without bound.
 *
 * The table takes one row per GPS ping: fifty masters pinging every ten seconds
 * over an eight-hour shift is ~144k rows a day. Left alone it reaches millions
 * within a month and drags down every query that touches it.
 */
class PruneMasterLocations extends Command
{
    protected $signature = 'locations:prune
        {--ping-days=7 : Keep untagged position pings for this many days}
        {--order-days=30 : Keep pings tagged with an order for this many days after that order closed}';

    protected $description = 'Delete master GPS pings that are past their retention window.';

    public function handle(MasterLocationRepository $locations): int
    {
        $pingDays = (int) $this->option('ping-days');
        $orderDays = (int) $this->option('order-days');

        if ($pingDays < 1 || $orderDays < 1) {
            $this->error('Retention windows must be at least one day.');

            return self::FAILURE;
        }

        $deleted = $locations->prune(
            pingCutoff: now()->subDays($pingDays),
            closedOrderCutoff: now()->subDays($orderDays),
        );

        $this->info("Pruned {$deleted} master location ping(s).");

        return self::SUCCESS;
    }
}
