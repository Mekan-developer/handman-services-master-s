<?php

namespace App\Events;

use App\Models\SubscriptionRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A client tapped "Buy" in the app and a plan request is waiting for review.
 *
 * Queued (`ShouldBroadcast`) so the API response is not held up by Reverb. The
 * payload is flattened at dispatch time and carries the plan name in both
 * languages: the request arrives in the app's locale, the admin panel may be
 * in the other one.
 */
class SubscriptionRequestSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @var array{id: int, client_name: string|null, client_phone: string|null, plan_name_ru: string|null, plan_name_tk: string|null} */
    public array $payload;

    public function __construct(SubscriptionRequest $request)
    {
        $this->payload = [
            'id' => $request->id,
            'client_name' => $request->client?->name,
            'client_phone' => $request->client?->phone,
            'plan_name_ru' => $request->plan?->name_ru,
            'plan_name_tk' => $request->plan?->name_tk,
        ];
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.subscription-requests'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'subscription-request.submitted';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
