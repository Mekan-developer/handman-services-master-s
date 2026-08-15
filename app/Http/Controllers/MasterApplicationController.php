<?php

namespace App\Http\Controllers;

use App\Actions\ReviewMasterApplicationAction;
use App\Exceptions\MasterApplicationException;
use App\Exceptions\SubscriptionException;
use App\Http\Requests\ApproveMasterApplicationRequest;
use App\Http\Requests\RejectMasterApplicationRequest;
use App\Http\Resources\MasterResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Traits\WithNotification;
use App\Repositories\MasterRepository;
use App\Repositories\SubscriptionPlanRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review queue for "become a master" applications submitted from the app.
 */
class MasterApplicationController extends Controller
{
    use WithNotification;

    public function __construct(private readonly MasterRepository $repository) {}

    public function index(SubscriptionPlanRepository $plans): Response
    {
        return Inertia::render('Masters/Applications', [
            'applications' => MasterResource::collection($this->repository->pendingApplications()),
            'subscriptionPlans' => SubscriptionPlanResource::collection($plans->active())->resolve(),
        ]);
    }

    public function approve(
        ApproveMasterApplicationRequest $request,
        int $id,
        ReviewMasterApplicationAction $action,
    ): RedirectResponse {
        $master = $this->repository->findOrFail($id);

        try {
            $action->approve($master, $request->user(), $request->validated());
            $this->notifySuccess('notifications.updated', ['resource' => __('resources.master')]);
        } catch (MasterApplicationException|SubscriptionException $e) {
            $this->notifyError($e->getMessage());
        }

        return redirect()->route('master-applications.index');
    }

    public function reject(
        RejectMasterApplicationRequest $request,
        int $id,
        ReviewMasterApplicationAction $action,
    ): RedirectResponse {
        $master = $this->repository->findOrFail($id);

        try {
            $action->reject($master, $request->user(), $request->validated('rejection_reason'));
            $this->notifySuccess('notifications.updated', ['resource' => __('resources.master')]);
        } catch (MasterApplicationException $e) {
            $this->notifyError($e->getMessage());
        }

        return redirect()->route('master-applications.index');
    }
}
