<?php

namespace App\Listeners;

use App\Events\SubscriptionRequestSubmitted;
use App\Models\User;
use App\Notifications\NewSubscriptionRequestNotification;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOnNewSubscriptionRequest
{
    public function __construct(private readonly UserRepository $repository) {}

    public function handle(SubscriptionRequestSubmitted $event): void
    {
        $notification = new NewSubscriptionRequestNotification($event->payload);

        /** @param Collection<int, User> $recipients */
        $this->repository->eachAdministrator(
            fn (Collection $recipients) => Notification::send($recipients, $notification)
        );
    }
}
