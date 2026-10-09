<?php

namespace App\Notifications\Push;

use App\Models\SubscriptionRequest;

/** To the requester: the administrator turned their plan request down. */
class SubscriptionRequestRejectedNotification extends PushNotification
{
    public function __construct(public SubscriptionRequest $request) {}

    protected function type(): string
    {
        return 'subscription_request.rejected';
    }

    protected function audience(): string
    {
        return self::AUDIENCE_MASTER;
    }

    protected function title(): string
    {
        return __('push.master.subscription_request_rejected.title');
    }

    /** The reason, when given, is what the user needs to act on — so it is the body. */
    protected function body(): string
    {
        return filled($this->request->rejection_reason)
            ? (string) $this->request->rejection_reason
            : __('push.master.subscription_request_rejected.body');
    }

    /** @return array<string, string> */
    protected function data(): array
    {
        return ['subscription_request_id' => (string) $this->request->id];
    }
}
