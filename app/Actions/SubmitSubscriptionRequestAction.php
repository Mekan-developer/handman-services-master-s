<?php

namespace App\Actions;

use App\Enums\SubscriptionRequestStatus;
use App\Exceptions\SubscriptionRequestException;
use App\Models\Client;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRequest;
use App\Repositories\SubscriptionRequestRepository;
use Illuminate\Support\Facades\DB;

/**
 * The "Buy" button on the app's plans screen. Grants nothing by itself — the
 * administrator gets in touch, settles the payment and approves or rejects.
 *
 * Open to any client, master or not: whether the request can be fulfilled is
 * the administrator's call at review time.
 */
class SubmitSubscriptionRequestAction
{
    public function __construct(private readonly SubscriptionRequestRepository $requests) {}

    /** @throws SubscriptionRequestException */
    public function handle(Client $client, SubscriptionPlan $plan): SubscriptionRequest
    {
        return DB::transaction(function () use ($client, $plan): SubscriptionRequest {
            if ($this->requests->pendingForClientLocked($client) !== null) {
                throw SubscriptionRequestException::alreadyPending();
            }

            return $this->requests->create([
                'client_id' => $client->id,
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionRequestStatus::Pending,
            ]);
        });
    }
}
