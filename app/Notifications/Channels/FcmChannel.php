<?php

namespace App\Notifications\Channels;

use App\Repositories\ClientDeviceRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Traits\Localizable;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * Push delivery through Firebase Cloud Messaging.
 *
 * A notification opts in with `FcmChannel::class` in `via()` and a
 * `toFcm(object $notifiable): CloudMessage` method; the notifiable lists its
 * tokens grouped by app language in `routeNotificationForFcm()`. The message is
 * built once per language, so every phone reads it in the language its app is
 * set to, and each group goes out as one multicast. Tokens Firebase reports as
 * dead are dropped right away so they are never retried.
 */
class FcmChannel
{
    use Localizable;

    public function __construct(
        private readonly Container $container,
        private readonly ClientDeviceRepository $devices,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        /** @var array<string, list<string>> $tokensByLocale */
        $tokensByLocale = $notifiable->routeNotificationFor('fcm', $notification) ?? [];

        if ($tokensByLocale === []) {
            return;
        }

        // Resolved only once there is someone to push to: building the client
        // reads the service-account credentials, and most recipients (every
        // account without the app installed) never need them.
        $messaging = $this->container->make(Messaging::class);

        $deadTokens = [];

        foreach ($tokensByLocale as $locale => $tokens) {
            /** @var CloudMessage $message */
            $message = $this->withLocale($locale, fn () => $notification->toFcm($notifiable));

            $report = $messaging->sendMulticast($message, $tokens);

            array_push($deadTokens, ...$report->invalidTokens(), ...$report->unknownTokens());
        }

        $this->devices->deleteTokens(array_values(array_unique($deadTokens)));
    }
}
