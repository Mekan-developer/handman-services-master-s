<?php

namespace App\Actions\Concerns;

use App\Exceptions\OrderException;
use App\Models\Master;

/**
 * Shared gate for every path that puts an order into a master's hands —
 * administrator assignment as well as the master's own response to an
 * auto-search offer.
 */
trait EnsuresMasterEligibility
{
    /** @throws OrderException */
    private function ensureMasterCanTakeOrders(Master $master): void
    {
        if (! $master->is_active || ! $master->hasActiveAccess()) {
            throw OrderException::masterAccessExpired();
        }

        if (! $master->is_available) {
            throw OrderException::masterUnavailable();
        }
    }
}
