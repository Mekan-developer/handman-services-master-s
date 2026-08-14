<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('subscriptions.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Active => 'green',
            self::Expired => 'gray',
            self::Cancelled => 'red',
        };
    }

    /** Terminal statuses — a subscription can never leave them. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled], true);
    }

    /**
     * Statuses that still grant access: the running subscription plus the ones
     * queued behind it (paid for, waiting for their turn).
     *
     * @return array<int, self>
     */
    public static function grantingAccess(): array
    {
        return [self::Active, self::Pending];
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Active, self::Cancelled], true),
            self::Active => in_array($to, [self::Expired, self::Cancelled], true),
            self::Expired, self::Cancelled => false,
        };
    }
}
