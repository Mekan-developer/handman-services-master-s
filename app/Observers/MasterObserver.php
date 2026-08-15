<?php

namespace App\Observers;

use App\Models\Master;

class MasterObserver
{
    /**
     * A master who loses the right to work — deactivated by an administrator, or
     * whose application was rejected — must not stay advertised as available.
     *
     * Tokens are deliberately left alone: the mobile app authenticates as a
     * client, and losing the master role must never sign someone out of the
     * client side of the app. `EnsureMaster` is what closes the master
     * endpoints.
     */
    public function updated(Master $master): void
    {
        $lostAccess = ($master->wasChanged('is_active') && ! $master->is_active)
            || ($master->wasChanged('status') && ! $master->isApproved());

        if ($lostAccess && $master->is_available) {
            $master->updateQuietly(['is_available' => false]);
        }
    }
}
