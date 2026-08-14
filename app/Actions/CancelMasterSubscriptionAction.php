<?php

namespace App\Actions;

use App\Actions\Concerns\SyncsMasterAccess;
use App\Enums\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Models\MasterSubscription;
use App\Repositories\MasterRepository;
use App\Repositories\MasterSubscriptionRepository;
use Illuminate\Support\Facades\DB;

class CancelMasterSubscriptionAction
{
    use SyncsMasterAccess;

    public function __construct(
        private readonly MasterSubscriptionRepository $subscriptions,
        private readonly MasterRepository $masters,
    ) {}

    /**
     * Cancel a subscription and recompute the master's access from whatever is left.
     * When nothing remains the deadline lands in the past, closing access at once.
     *
     * @throws SubscriptionException
     */
    public function handle(MasterSubscription $subscription): MasterSubscription
    {
        if ($subscription->status->isFinal()) {
            throw SubscriptionException::alreadyFinal();
        }

        if (! $subscription->status->canTransitionTo(SubscriptionStatus::Cancelled)) {
            throw SubscriptionException::invalidTransition(
                $subscription->status->value,
                SubscriptionStatus::Cancelled->value,
            );
        }

        return DB::transaction(function () use ($subscription): MasterSubscription {
            $cancelled = $this->subscriptions->updateStatus($subscription, SubscriptionStatus::Cancelled);

            $this->syncMasterAccess($cancelled->master, $this->subscriptions, $this->masters);

            return $cancelled;
        });
    }
}
