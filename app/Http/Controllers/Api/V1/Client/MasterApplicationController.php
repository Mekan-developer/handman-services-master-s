<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\SubmitMasterApplicationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Client\SubmitMasterApplicationRequest;
use App\Http\Resources\Api\V1\Client\MasterApplicationResource;
use App\Repositories\MasterRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Become a master" — the client half of the flow. Everyone signs up as a
 * client; this is where they apply for a master profile and check the verdict.
 */
class MasterApplicationController extends Controller
{
    /**
     * Current application, or `null` when the client has never applied — the app
     * uses this to decide between showing the form and showing the status.
     */
    public function show(Request $request, MasterRepository $masters): JsonResponse
    {
        $master = $masters->findByClient($request->user());

        return response()->json([
            'data' => $master === null
                ? null
                : new MasterApplicationResource($master->load('city', 'categories')),
        ]);
    }

    public function store(SubmitMasterApplicationRequest $request, SubmitMasterApplicationAction $action): JsonResponse
    {
        $master = $action->handle($request->user(), $request->validated());

        return response()->json([
            'data' => new MasterApplicationResource($master->load('city', 'categories')),
        ], 201);
    }
}
