<?php

namespace App\Actions;

use App\Models\MasterSubscription;
use App\Notifications\Push\SubscriptionExpiringNotification;
use App\Repositories\MasterSubscriptionRepository;

/**
 * Warns masters a day before their access runs out. Each subscription is
 * reminded at most once; a master with a renewal already queued is skipped,
 * since nothing actually stops for them.
 */
class RemindExpiringSubscriptionsAction
{
    public const REMIND_HOURS_BEFORE = 24;

    public function __construct(private readonly MasterSubscriptionRepository $subscriptions) {}

    /** @return int Number of masters reminded */
    public function handle(): int
    {
        $due = $this->subscriptions->dueForExpiryReminder(now()->addHours(self::REMIND_HOURS_BEFORE));

        $due->each(function (MasterSubscription $subscription): void {
            $this->subscriptions->markExpiryReminded($subscription);

            $subscription->master?->notify(new SubscriptionExpiringNotification($subscription));
        });

        return $due->count();
    }
}
