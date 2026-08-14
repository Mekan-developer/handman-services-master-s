<?php

namespace App\Http\Controllers;

use App\Actions\ChangeMasterSubscriptionStatusAction;
use App\Actions\DeleteMasterSubscriptionAction;
use App\Actions\IssueMasterSubscriptionAction;
use App\Actions\UpdateMasterSubscriptionAction;
use App\Enums\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Http\Requests\IssueMasterSubscriptionRequest;
use App\Http\Requests\UpdateMasterSubscriptionRequest;
use App\Http\Requests\UpdateMasterSubscriptionStatusRequest;
use App\Http\Resources\MasterResource;
use App\Http\Resources\MasterSubscriptionResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Traits\WithNotification;
use App\Repositories\MasterRepository;
use App\Repositories\MasterSubscriptionRepository;
use App\Repositories\SubscriptionPlanRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterSubscriptionController extends Controller
{
    use WithNotification;

    public function __construct(
        private readonly MasterSubscriptionRepository $subscriptions,
        private readonly SubscriptionPlanRepository $plans,
        private readonly MasterRepository $masters,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['master_id', 'status']);

        return Inertia::render('Subscriptions/Index', [
            'plans' => SubscriptionPlanResource::collection($this->plans->all()),
            'subscriptions' => MasterSubscriptionResource::collection($this->subscriptions->paginate(15, $filters)),
            'masters' => MasterResource::collection($this->masters->allForSelect())->resolve(),
            'statuses' => collect(SubscriptionStatus::cases())->map(fn (SubscriptionStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'stats' => $this->subscriptions->stats(),
            'filters' => $filters,
        ]);
    }

    public function store(IssueMasterSubscriptionRequest $request, int $masterId, IssueMasterSubscriptionAction $action): RedirectResponse
    {
        $master = $this->masters->findOrFail($masterId);
        $validated = $request->validated();

        try {
            $action->handle(
                $master,
                $this->plans->findOrFail((int) $validated['subscription_plan_id']),
                $request->user(),
                isset($validated['price_paid']) ? (float) $validated['price_paid'] : null,
                $validated['note'] ?? null,
            );
            $this->notifySuccess('notifications.created', ['resource' => __('resources.subscription')]);
        } catch (SubscriptionException $e) {
            $this->notifyError($e->getMessage());
        }

        return redirect()->route('subscriptions.index');
    }

    public function updateStatus(UpdateMasterSubscriptionStatusRequest $request, int $id, ChangeMasterSubscriptionStatusAction $action): RedirectResponse
    {
        $subscription = $this->subscriptions->findOrFail($id);

        try {
            $action->handle($subscription, SubscriptionStatus::from($request->validated()['status']));
            $this->notifySuccess('notifications.updated', ['resource' => __('resources.subscription')]);
        } catch (SubscriptionException $e) {
            $this->notifyError($e->getMessage());
        }

        return redirect()->route('subscriptions.index');
    }

    public function update(UpdateMasterSubscriptionRequest $request, int $id, UpdateMasterSubscriptionAction $action): RedirectResponse
    {
        $subscription = $this->subscriptions->findOrFail($id);
        $action->handle($subscription, $request->validated());
        $this->notifySuccess('notifications.updated', ['resource' => __('resources.subscription')]);

        return redirect()->route('subscriptions.index');
    }

    public function destroy(int $id, DeleteMasterSubscriptionAction $action): RedirectResponse
    {
        $subscription = $this->subscriptions->findOrFail($id);
        $action->handle($subscription);
        $this->notifySuccess('notifications.deleted', ['resource' => __('resources.subscription')]);

        return redirect()->route('subscriptions.index');
    }
}
