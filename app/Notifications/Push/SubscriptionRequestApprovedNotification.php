<?php

namespace App\Notifications\Push;

use App\Models\SubscriptionRequest;

/** To the master: the administrator approved their plan request and access is extended. */
class SubscriptionRequestApprovedNotification extends PushNotification
{
    public function __construct(public SubscriptionRequest $request) {}

    protected function type(): string
    {
        return 'subscription_request.approved';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_MASTER;
    }

    protected function title(): string
    {
        return __('push.master.subscription_request_approved.title');
    }

    protected function body(): string
    {
        return __('push.master.subscription_request_approved.body', [
            'plan' => $this->request->subscription?->plan_name ?? '',
            'date' => $this->request->subscription?->expires_at?->format('d.m.Y') ?? '',
        ]);
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return [
            'subscription_request_id' => (string) $this->request->id,
            'subscription_id' => (string) $this->request->master_subscription_id,
        ];
    }
}
