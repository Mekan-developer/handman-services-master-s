<?php

namespace App\Listeners;

use App\Events\MasterAssigned;
use App\Notifications\Push\MasterAssignedNotification;
use App\Notifications\Push\ResponseApprovedNotification;

/** A master was confirmed on an order: the client learns who, the master learns they won it. */
class NotifyAboutMasterAssignment
{
    public function handle(MasterAssigned $event): void
    {
        $order = $event->order;

        $order->client?->notify(new MasterAssignedNotification($order));
        $order->master?->notify(new ResponseApprovedNotification($order));
    }
}
