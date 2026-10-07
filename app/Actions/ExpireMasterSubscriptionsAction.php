<?php

namespace App\Actions;

use App\Actions\Concerns\SyncsMasterAccess;
use App\Enums\SubscriptionStatus;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Notifications\Push\SubscriptionExpiredNotification;
use App\Repositories\MasterRepository;
use App\Repositories\MasterSubscriptionRepository;
use Illuminate\Support\Facades\DB;

class ExpireMasterSubscriptionsAction
{
    use SyncsMasterAccess;

    public function __construct(
        private readonly MasterSubscriptionRepository $subscriptions,
        private readonly MasterRepository $masters,
    ) {}

    /**
     * Roll the subscription clock forward in one pass: retire subscriptions that
     * ran out, promote the queued ones whose turn has come, then re-derive every
     * touched master's access deadline. Masters left without access are told so
     * once the transaction has committed.
     *
     * @return array{expired: int, activated: int}
     */
    public function handle(): array
    {
        /** @var array<int, Master> $touched */
        $touched = [];

        /** @var array<int, MasterSubscription> $expiredByMaster */
        $expiredByMaster = [];

        /** @var array<int, Master> $lostAccess */
        $lostAccess = [];

        $expired = 0;
        $activated = 0;

        DB::transaction(function () use (&$touched, &$expiredByMaster, &$lostAccess, &$expired, &$activated): void {
            $this->subscriptions->dueForExpiry()->each(
                function (MasterSubscription $subscription) use (&$touched, &$expiredByMaster, &$expired): void {
                    $this->subscriptions->updateStatus($subscription, SubscriptionStatus::Expired);
                    $expired++;

                    if ($subscription->master !== null) {
                        $touched[$subscription->master->id] = $subscription->master;
                        $expiredByMaster[$subscription->master->id] = $subscription;
                    }
                }
            );

            $this->subscriptions->dueForActivation()->each(
                function (MasterSubscription $subscription) use (&$touched, &$activated): void {
                    if ($subscription->master === null) {
                        return;
                    }

                    // Never promote past a subscription that is still running —
                    // the queue moves one step at a time.
                    if ($this->subscriptions->activeForMaster($subscription->master) !== null) {
                        return;
                    }

                    $this->subscriptions->updateStatus($subscription, SubscriptionStatus::Active);
                    $activated++;

                    $touched[$subscription->master->id] = $subscription->master;
                }
            );

            foreach ($touched as $masterId => $master) {
                $synced = $this->syncMasterAccess($master, $this->subscriptions, $this->masters);

                if (isset($expiredByMaster[$masterId]) && ! $synced->hasActiveAccess()) {
                    $lostAccess[$masterId] = $synced;
                }
            }
        });

        foreach ($lostAccess as $masterId => $master) {
            $master->notify(new SubscriptionExpiredNotification($expiredByMaster[$masterId]));
        }

        return ['expired' => $expired, 'activated' => $activated];
    }
}
