<?php

namespace App\Notifications\Push;

use App\Models\MasterSubscription;

/** To the master: their last paid-for subscription ends soon. */
class SubscriptionExpiringNotification extends PushNotification
{
    public function __construct(public MasterSubscription $subscription) {}

    protected function type(): string
    {
        return 'subscription.expiring';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_MASTER;
    }

    protected function title(): string
    {
        return __('push.master.subscription_expiring.title');
    }

    protected function body(): string
    {
        return __('push.master.subscription_expiring.body', [
            'plan' => $this->subscription->plan_name,
            'date' => $this->subscription->expires_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
        ]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return [
            'subscription_id' => (string) $this->subscription->id,
            'expires_at' => (string) $this->subscription->expires_at?->toIso8601String(),
        ];
    }
}
