<?php

namespace App\Actions;

use App\Actions\Concerns\SyncsMasterAccess;
use App\Models\MasterSubscription;
use App\Repositories\MasterRepository;
use App\Repositories\MasterSubscriptionRepository;
use Illuminate\Support\Facades\DB;

class DeleteMasterSubscriptionAction
{
    use SyncsMasterAccess;

    public function __construct(
        private readonly MasterSubscriptionRepository $subscriptions,
        private readonly MasterRepository $masters,
    ) {}

    /** Erasing a subscription must also give back the access it was granting. */
    public function handle(MasterSubscription $subscription): void
    {
        $master = $subscription->master;

        DB::transaction(function () use ($subscription, $master): void {
            $this->subscriptions->delete($subscription);

            if ($master !== null) {
                $this->syncMasterAccess($master, $this->subscriptions, $this->masters);
            }
        });
    }
}
