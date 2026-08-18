<?php

namespace App\Exceptions;

/**
 * Deleting a client account takes the master profile hanging off it with them,
 * so the master's own delete rules apply here too — just worded from the client
 * side, which is where the administrator is standing.
 */
class ClientException extends ApiException
{
    public static function masterHasCompletedOrders(int $orderCount): self
    {
        return new self((string) __('clients.errors.delete_master_has_completed_orders', ['count' => $orderCount]));
    }

    public static function masterHasActiveOrders(int $orderCount): self
    {
        return new self((string) __('clients.errors.delete_master_has_active_orders', ['count' => $orderCount]));
    }
}
