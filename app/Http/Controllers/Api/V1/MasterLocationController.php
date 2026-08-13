<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateMasterLocationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMasterLocationRequest;
use App\Http\Resources\Api\V1\MasterLocationResource;
use App\Models\Master;
use Illuminate\Http\JsonResponse;

class MasterLocationController extends Controller
{
    /**
     * Store a live GPS ping for the authenticated master.
     *
     * `ensure.master` has already rejected inactive masters and expired access,
     * so the only remaining check is that the {master} path segment refers to
     * the token owner — a master may never post locations for somebody else.
     */
    public function store(
        StoreMasterLocationRequest $request,
        int $masterId,
        UpdateMasterLocationAction $action,
    ): JsonResponse {
        /** @var Master $master */
        $master = $request->user();

        if ($master->id !== $masterId) {
            return response()->json(['message' => __('api.master.location_owner_mismatch')], 403);
        }

        $location = $action->handle($master, $request->validated());

        return (new MasterLocationResource($location))
            ->response()
            ->setStatusCode(201);
    }
}
