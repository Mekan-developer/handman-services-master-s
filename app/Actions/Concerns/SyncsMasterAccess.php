<?php

namespace App\Actions\Concerns;

use App\Models\Master;
use App\Repositories\MasterRepository;
use App\Repositories\MasterSubscriptionRepository;

/**
 * Keeps `masters.access_expires_at` derived from subscriptions and nothing else.
 *
 * Every action that creates, cancels or expires a subscription funnels through
 * here, so the column has exactly one writer and can never drift.
 */
trait SyncsMasterAccess
{
    private function syncMasterAccess(
        Master $master,
        MasterSubscriptionRepository $subscriptions,
        MasterRepository $masters,
    ): Master {
        $endsAt = $subscriptions->accessEndsAtFor($master);

        // No paid-for time left: close access by putting the deadline in the past.
        // Null is not an option — it means "unlimited" to Master::hasActiveAccess().
        return $masters->updateAccessExpiry($master, $endsAt ?? now());
    }
}
