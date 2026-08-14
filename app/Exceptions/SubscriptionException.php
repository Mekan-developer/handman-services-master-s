<?php

namespace App\Exceptions;

class SubscriptionException extends ApiException
{
    /** The chosen plan is disabled, so no new subscription may be issued from it. */
    public static function planNotAvailable(): self
    {
        return new self((string) __('subscriptions.errors.plan_not_available'));
    }

    /** The plan was soft deleted after the picker was rendered. */
    public static function planDeleted(): self
    {
        return new self((string) __('subscriptions.errors.plan_deleted'));
    }

    public static function masterInactive(): self
    {
        return new self((string) __('subscriptions.errors.master_inactive'));
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self((string) __('subscriptions.errors.invalid_transition', ['from' => $from, 'to' => $to]));
    }

    /** Manual activation while another subscription is still running. */
    public static function alreadyActive(): self
    {
        return new self((string) __('subscriptions.errors.already_active'));
    }

    public static function alreadyFinal(): self
    {
        return new self((string) __('subscriptions.errors.already_final'));
    }
}
