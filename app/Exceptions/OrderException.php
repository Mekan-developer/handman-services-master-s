<?php

namespace App\Exceptions;

class OrderException extends ApiException
{
    public static function masterAccessExpired(): self
    {
        return new self((string) __('orders.errors.master_inactive'));
    }

    public static function masterUnavailable(): self
    {
        return new self((string) __('orders.errors.master_unavailable'));
    }

    public static function masterNotAssigned(): self
    {
        return new self((string) __('orders.errors.master_not_assigned'));
    }

    public static function cityMismatch(): self
    {
        return new self((string) __('orders.errors.city_mismatch'));
    }

    public static function categoryMismatch(): self
    {
        return new self((string) __('orders.errors.category_mismatch'));
    }

    public static function alreadyFinal(): self
    {
        return new self((string) __('orders.errors.already_final'));
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self((string) __('orders.errors.invalid_transition', ['from' => $from, 'to' => $to]));
    }

    public static function notEditable(): self
    {
        return new self((string) __('orders.errors.not_editable'));
    }

    public static function cannotCancelAssignedOrder(): self
    {
        return new self((string) __('orders.errors.cannot_cancel_assigned'));
    }

    public static function tooManyPhotos(): self
    {
        return new self((string) __('orders.errors.too_many_photos'));
    }

    public static function notCompletedYet(): self
    {
        return new self((string) __('orders.errors.not_completed_yet'));
    }

    public static function alreadyReviewed(): self
    {
        return new self((string) __('orders.errors.already_reviewed'));
    }

    /** Another master won the atomic claim first. */
    public static function alreadyClaimed(): self
    {
        return new self((string) __('orders.errors.already_claimed'));
    }

    /** The master sits outside the order's currently active search radius. */
    public static function outOfSearchRadius(): self
    {
        return new self((string) __('orders.errors.out_of_search_radius'));
    }

    /** No GPS ping was ever recorded for this master, so distance cannot be checked. */
    public static function masterLocationUnknown(): self
    {
        return new self((string) __('orders.errors.master_location_unknown'));
    }

    /** This master already has a response (pending, approved or rejected) on this order. */
    public static function alreadyResponded(): self
    {
        return new self((string) __('orders.errors.already_responded'));
    }

    /** The master placed this order on their own client account. */
    public static function ownOrder(): self
    {
        return new self((string) __('orders.errors.own_order'));
    }

    /** The response was already approved or rejected — the client can't decide on it twice. */
    public static function responseNotPending(): self
    {
        return new self((string) __('orders.errors.response_not_pending'));
    }

    /** The auto-search can only be (re)started while the order is pending and unassigned. */
    public static function searchRestartNotAllowed(): self
    {
        return new self((string) __('orders.errors.search_restart_not_allowed'));
    }

    /**
     * A GPS ping was tagged with an order that is already finished or cancelled.
     * Accepting it would keep feeding the client's map after tracking should
     * have stopped, so the master app is told to drop the tag instead.
     */
    public static function orderNotTrackable(): self
    {
        return new self((string) __('orders.errors.order_not_trackable'));
    }
}
