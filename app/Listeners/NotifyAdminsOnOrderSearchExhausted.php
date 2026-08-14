<?php

namespace App\Listeners;

use App\Events\OrderSearchExhausted;
use App\Models\User;
use App\Notifications\OrderSearchExhaustedNotification;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOnOrderSearchExhausted
{
    public function __construct(private readonly UserRepository $repository) {}

    public function handle(OrderSearchExhausted $event): void
    {
        $notification = new OrderSearchExhaustedNotification($event->order);

        /** @param Collection<int, User> $recipients */
        $this->repository->eachOrderNotifiable(
            fn (Collection $recipients) => Notification::send($recipients, $notification)
        );
    }
}
