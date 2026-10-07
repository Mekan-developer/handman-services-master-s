<?php

namespace App\Notifications\Push;

use App\Models\MasterSubscription;

/** To the master: their subscription ran out and nothing is queued behind it. */
class SubscriptionExpiredNotification extends PushNotification
{
    public function __construct(public MasterSubscription $subscription) {}

    protected function type(): string
    {
        return 'subscription.expired';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_MASTER;
    }

    protected function title(): string
    {
        return __('push.master.subscription_expired.title');
    }

    protected function body(): string
    {
        return __('push.master.subscription_expired.body', ['plan' => $this->subscription->plan_name]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return ['subscription_id' => (string) $this->subscription->id];
    }
}
