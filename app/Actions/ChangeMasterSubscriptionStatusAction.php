<?php

namespace App\Actions;

use App\Actions\Concerns\SyncsMasterAccess;
use App\Enums\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Models\MasterSubscription;
use App\Repositories\MasterRepository;
use App\Repositories\MasterSubscriptionRepository;
use Illuminate\Support\Facades\DB;

class ChangeMasterSubscriptionStatusAction
{
    use SyncsMasterAccess;

    public function __construct(
        private readonly MasterSubscriptionRepository $subscriptions,
        private readonly MasterRepository $masters,
        private readonly CancelMasterSubscriptionAction $cancel,
    ) {}

    /**
     * Manual status change by an administrator. Cancellation has its own action;
     * everything else (starting a queued subscription early, closing a running
     * one) is handled here. Allowed moves live in {@see SubscriptionStatus::canTransitionTo()}.
     *
     * @throws SubscriptionException
     */
    public function handle(MasterSubscription $subscription, SubscriptionStatus $status): MasterSubscription
    {
        if ($status === SubscriptionStatus::Cancelled) {
            return $this->cancel->handle($subscription);
        }

        if ($subscription->status->isFinal()) {
            throw SubscriptionException::alreadyFinal();
        }

        if (! $subscription->status->canTransitionTo($status)) {
            throw SubscriptionException::invalidTransition($subscription->status->value, $status->value);
        }

        if ($status === SubscriptionStatus::Active
            && $this->subscriptions->activeForMaster($subscription->master) !== null) {
            throw SubscriptionException::alreadyActive();
        }

        return DB::transaction(function () use ($subscription, $status): MasterSubscription {
            $updated = $this->subscriptions->updateStatus($subscription, $status);

            $this->syncMasterAccess($updated->master, $this->subscriptions, $this->masters);

            return $updated;
        });
    }
}
