<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOnNewOrder
{
    public function __construct(private readonly UserRepository $repository) {}

    public function handle(OrderCreated $event): void
    {
        $notification = new NewOrderNotification($event->order);

        /** @param Collection<int, User> $recipients */
        $this->repository->eachOrderNotifiable(
            fn (Collection $recipients) => Notification::send($recipients, $notification)
        );
    }
}
