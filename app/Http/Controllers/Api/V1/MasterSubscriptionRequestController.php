<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SubmitSubscriptionRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSubscriptionRequestRequest;
use App\Http\Resources\Api\V1\SubscriptionRequestResource;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRequestRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Buy" on the app's plans screen. Runs on the plain client token (`ensure.client`),
 * not `ensure.master`: a master whose access ran out must still be able to renew,
 * and the app decides itself who sees the button.
 */
class MasterSubscriptionRequestController extends Controller
{
    public function __construct(private readonly SubscriptionRequestRepository $requests) {}

    /** The client's requests, newest first — the app reads the first one for the status banner. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => SubscriptionRequestResource::collection($this->requests->forClient($request->user()))->resolve(),
        ]);
    }

    public function store(
        StoreSubscriptionRequestRequest $request,
        SubscriptionPlanRepository $plans,
        SubmitSubscriptionRequestAction $action,
    ): JsonResponse {
        $subscriptionRequest = $action->handle(
            $request->user(),
            $plans->findOrFail((int) $request->validated('plan_id')),
        );

        return response()->json([
            'message' => __('api.subscription_request.submitted'),
            'data' => (new SubscriptionRequestResource($subscriptionRequest->load('plan')))->resolve(),
        ], 201);
    }
}
