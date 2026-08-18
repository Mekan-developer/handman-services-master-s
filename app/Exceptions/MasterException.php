<?php

namespace App\Exceptions;

class MasterException extends ApiException
{
    /**
     * Completed orders are the master's work history — the client who ordered
     * them keeps seeing the job, the review and the before/after photos. Erasing
     * the master would gut all of it, so the profile stays.
     */
    public static function hasCompletedOrders(int $orderCount): self
    {
        return new self((string) __('masters.errors.delete_has_completed_orders', ['count' => $orderCount]));
    }

    /**
     * A job in flight. `orders.master_id` is ON DELETE SET NULL, so deleting now
     * would leave an order sitting in `assigned` with nobody assigned to it.
     */
    public static function hasActiveOrders(int $orderCount): self
    {
        return new self((string) __('masters.errors.delete_has_active_orders', ['count' => $orderCount]));
    }
}
