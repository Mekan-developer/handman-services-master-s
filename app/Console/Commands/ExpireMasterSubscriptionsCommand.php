<?php

namespace App\Console\Commands;

use App\Actions\ExpireMasterSubscriptionsAction;
use Illuminate\Console\Command;

class ExpireMasterSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Retire master subscriptions past their end date, promote queued ones and resync master access';

    public function handle(ExpireMasterSubscriptionsAction $action): int
    {
        ['expired' => $expired, 'activated' => $activated] = $action->handle();

        $this->info("Expired {$expired} subscription(s), activated {$activated}.");

        return self::SUCCESS;
    }
}
