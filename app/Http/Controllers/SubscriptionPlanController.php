<?php

namespace App\Http\Controllers;

use App\Actions\CreateSubscriptionPlanAction;
use App\Actions\DeleteSubscriptionPlanAction;
use App\Actions\ToggleSubscriptionPlanStatusAction;
use App\Actions\UpdateSubscriptionPlanAction;
use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Http\Traits\WithNotification;
use App\Repositories\SubscriptionPlanRepository;
use Illuminate\Http\RedirectResponse;

class SubscriptionPlanController extends Controller
{
    use WithNotification;

    public function __construct(private readonly SubscriptionPlanRepository $repository) {}

    public function store(StoreSubscriptionPlanRequest $request, CreateSubscriptionPlanAction $action): RedirectResponse
    {
        $action->handle($request->validated());
        $this->notifySuccess('notifications.created', ['resource' => __('resources.subscription_plan')]);

        return redirect()->route('subscriptions.index', ['tab' => 'plans']);
    }

    public function update(UpdateSubscriptionPlanRequest $request, int $id, UpdateSubscriptionPlanAction $action): RedirectResponse
    {
        $plan = $this->repository->findOrFail($id);
        $action->handle($plan, $request->validated());
        $this->notifySuccess('notifications.updated', ['resource' => __('resources.subscription_plan')]);

        return redirect()->route('subscriptions.index', ['tab' => 'plans']);
    }

    public function toggle(int $id, ToggleSubscriptionPlanStatusAction $action): RedirectResponse
    {
        $plan = $this->repository->findOrFail($id);
        $action->handle($plan);
        $this->notifySuccess('notifications.updated', ['resource' => __('resources.subscription_plan')]);

        return redirect()->route('subscriptions.index', ['tab' => 'plans']);
    }

    public function destroy(int $id, DeleteSubscriptionPlanAction $action): RedirectResponse
    {
        $plan = $this->repository->findOrFail($id);
        $action->handle($plan);
        $this->notifySuccess('notifications.deleted', ['resource' => __('resources.subscription_plan')]);

        return redirect()->route('subscriptions.index', ['tab' => 'plans']);
    }
}
