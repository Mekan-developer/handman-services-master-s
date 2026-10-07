<?php

namespace App\Listeners;

use App\Events\OrderResponseWithdrawn;
use App\Notifications\Push\OrderCancelledNotification;

/** The order a master responded to was cancelled before anyone was chosen. */
class NotifyMasterAboutWithdrawnResponse
{
    public function handle(OrderResponseWithdrawn $event): void
    {
        $event->response->master?->notify(new OrderCancelledNotification($event->response->order));
    }
}
