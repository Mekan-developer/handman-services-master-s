<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\RegisterClientDeviceAction;
use App\Actions\RemoveClientDeviceAction;
use App\Enums\DevicePlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Client\RegisterDeviceRequest;
use App\Http\Requests\Api\V1\Client\RemoveDeviceRequest;
use App\Http\Resources\Api\V1\Client\ClientDeviceResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;

/**
 * FCM registration tokens of the phones a client is signed in on.
 */
class ClientDeviceController extends Controller
{
    public function store(RegisterDeviceRequest $request, RegisterClientDeviceAction $action): ClientDeviceResource
    {
        /** @var Client $client */
        $client = $request->user();

        $device = $action->handle(
            $client,
            $request->validated('token'),
            DevicePlatform::from($request->validated('platform')),
            // Already resolved from X-Locale by the SetLocale middleware.
            app()->getLocale(),
        );

        return new ClientDeviceResource($device);
    }

    public function destroy(RemoveDeviceRequest $request, RemoveClientDeviceAction $action): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user();

        $action->handle($client, $request->validated('token'));

        return response()->json(null, 204);
    }
}
