<?php

namespace App\Console\Commands;

use App\Actions\RemindExpiringSubscriptionsAction;
use Illuminate\Console\Command;

class RemindExpiringSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:remind-expiring';

    protected $description = 'Push a reminder to masters whose subscription ends within a day and has no renewal queued';

    public function handle(RemindExpiringSubscriptionsAction $action): int
    {
        $reminded = $action->handle();

        $this->info("Reminded {$reminded} master(s).");

        return self::SUCCESS;
    }
}
