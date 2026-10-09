<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Bell entry for administrators: a plan request from the app awaits review. */
class NewSubscriptionRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{id: int, client_name: string|null, client_phone: string|null, plan_name_ru: string|null, plan_name_tk: string|null}  $payload
     */
    public function __construct(public array $payload) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_subscription_request',
            'subscription_request_id' => $this->payload['id'],
            'client_name' => $this->payload['client_name'],
            'phone' => $this->payload['client_phone'],
            'plan_name_ru' => $this->payload['plan_name_ru'],
            'plan_name_tk' => $this->payload['plan_name_tk'],
        ];
    }
}
