<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MasterSubscriptionResource;
use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Http\Resources\Api\V1\SubscriptionRequestResource;
use App\Repositories\MasterSubscriptionRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRequestRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanRepository $plans,
        private readonly MasterSubscriptionRepository $subscriptions,
    ) {}

    /**
     * Public price list. Deliberately unauthenticated: a master whose access ran
     * out cannot obtain a token, yet still has to see what to buy.
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => SubscriptionPlanResource::collection($this->plans->active())->resolve(),
        ]);
    }

    /**
     * The authenticated master's own subscription — current one plus history,
     * and the plan request still waiting for the administrator, if any.
     */
    public function current(Request $request, SubscriptionRequestRepository $requests): JsonResponse
    {
        $master = $request->user();
        $current = $this->subscriptions->currentForMaster($master);
        $pendingRequest = $requests->pendingForClient($master->client);

        return response()->json([
            'data' => [
                'current' => $current !== null ? (new MasterSubscriptionResource($current))->resolve() : null,
                'access_expires_at' => $master->access_expires_at?->toDateString(),
                'has_active_access' => $master->hasActiveAccess(),
                'pending_request' => $pendingRequest !== null ? (new SubscriptionRequestResource($pendingRequest))->resolve() : null,
                'history' => MasterSubscriptionResource::collection($this->subscriptions->forMaster($master))->resolve(),
            ],
        ]);
    }
}
