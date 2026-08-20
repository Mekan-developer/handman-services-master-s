<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateMasterProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateMasterProfileRequest;
use App\Http\Resources\Api\V1\MasterProfileResource;
use App\Models\Master;
use Illuminate\Http\Request;

class MasterProfileController extends Controller
{
    public function __construct(private readonly UpdateMasterProfileAction $updateProfile) {}

    public function show(Request $request): MasterProfileResource
    {
        return new MasterProfileResource(
            $request->user()->load('city', 'categories')
        );
    }

    /**
     * Trade details the master maintains themselves. `EnsureMaster` has already
     * resolved the request user to the authenticated master profile, so there is
     * nothing else to authorize here.
     */
    public function update(UpdateMasterProfileRequest $request): MasterProfileResource
    {
        /** @var Master $master */
        $master = $request->user();

        $updated = $this->updateProfile->handle($master, $request->validated());

        return new MasterProfileResource($updated->load('city', 'categories'));
    }
}
