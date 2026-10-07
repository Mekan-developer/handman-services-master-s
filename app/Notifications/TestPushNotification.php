<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

/**
 * Sent by `php artisan fcm:test` to check the Firebase setup end to end.
 * Deliberately not queued — the command should fail loudly, not in a worker.
 */
class TestPushNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(object $notifiable): CloudMessage
    {
        return CloudMessage::new()
            ->withNotification(FcmNotification::create($this->title, $this->body))
            ->withData(['type' => 'test']);
    }
}
