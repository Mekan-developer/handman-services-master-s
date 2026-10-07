<?php

namespace App\Listeners;

use App\Events\MasterRespondedToOrder;
use App\Notifications\Push\MasterRespondedNotification;

class NotifyClientAboutMasterResponse
{
    public function handle(MasterRespondedToOrder $event): void
    {
        $event->response->order->client?->notify(new MasterRespondedNotification($event->response));
    }
}
