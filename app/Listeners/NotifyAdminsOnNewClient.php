<?php

namespace App\Listeners;

use App\Events\ClientCreated;
use App\Models\User;
use App\Notifications\NewClientNotification;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOnNewClient
{
    public function __construct(private readonly UserRepository $repository) {}

    public function handle(ClientCreated $event): void
    {
        $notification = new NewClientNotification($event->client);

        /** @param Collection<int, User> $recipients */
        $this->repository->eachOrderNotifiable(
            fn (Collection $recipients) => Notification::send($recipients, $notification)
        );
    }
}
