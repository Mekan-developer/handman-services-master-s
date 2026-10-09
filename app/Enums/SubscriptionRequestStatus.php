<?php

namespace App\Enums;

/**
 * Lifecycle of a "Buy" tap in the app. Every request starts `Pending`; only an
 * administrator moves it, and both verdicts are final.
 */
enum SubscriptionRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('subscription_requests.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}
