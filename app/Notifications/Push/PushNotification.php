<?php

namespace App\Notifications\Push;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

/**
 * Base for every push the mobile app receives.
 *
 * Queued, so a slow Firebase round-trip never holds up the API response that
 * triggered it. Title and body are rendered by FcmChannel once per app
 * language, so subclasses simply call `__()`.
 *
 * The data payload always carries `type` (what happened) and `audience`
 * (`client` or `master` — which part of the app should open on tap).
 */
abstract class PushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const AUDIENCE_CLIENT = 'client';

    public const AUDIENCE_MASTER = 'master';

    abstract protected function type(): string;

    abstract protected function audience(): string;

    abstract protected function title(): string;

    abstract protected function body(): string;

    /** @return array<string, string> */
    protected function data(): array
    {
        return [];
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(object $notifiable): CloudMessage
    {
        return CloudMessage::new()
            ->withNotification(FcmNotification::create($this->title(), $this->body()))
            ->withData([
                'type' => $this->type(),
                'audience' => $this->audience(),
                ...$this->data(),
            ])
            ->withAndroidConfig(AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => ['sound' => 'default'],
            ]))
            ->withApnsConfig(ApnsConfig::fromArray([
                'payload' => ['aps' => ['sound' => 'default']],
            ]));
    }
}
