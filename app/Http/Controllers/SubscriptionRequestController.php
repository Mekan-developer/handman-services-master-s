<?php

namespace App\Http\Controllers;

use App\Actions\ReviewSubscriptionRequestAction;
use App\Enums\SubscriptionRequestStatus;
use App\Exceptions\SubscriptionException;
use App\Exceptions\SubscriptionRequestException;
use App\Http\Requests\ApproveSubscriptionRequestRequest;
use App\Http\Requests\RejectSubscriptionRequestRequest;
use App\Http\Resources\SubscriptionRequestResource;
use App\Http\Traits\WithNotification;
use App\Repositories\SubscriptionRequestRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review queue for plan purchases requested from the app's "Buy" button.
 */
class SubscriptionRequestController extends Controller
{
    use WithNotification;

    /** Tab value for "every status" — an empty one would drop out of the URL and fall back to pending. */
    private const ALL_STATUSES = 'all';

    public function __construct(private readonly SubscriptionRequestRepository $requests) {}

    public function index(Request $request): Response
    {
        // The queue is what the administrator comes here for, so it is the default
        // tab; `all` (or any unknown value) shows every request.
        $status = SubscriptionRequestStatus::tryFrom(
            (string) $request->query('status', SubscriptionRequestStatus::Pending->value),
        );

        return Inertia::render('Subscriptions/Requests', [
            'requests' => SubscriptionRequestResource::collection(
                $this->requests->paginate(15, ['status' => $status?->value]),
            ),
            'statuses' => collect(SubscriptionRequestStatus::cases())->map(fn (SubscriptionRequestStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'filters' => ['status' => $status?->value ?? self::ALL_STATUSES],
        ]);
    }

    public function approve(
        ApproveSubscriptionRequestRequest $request,
        int $id,
        ReviewSubscriptionRequestAction $action,
    ): RedirectResponse {
        $subscriptionRequest = $this->requests->findOrFail($id);
        $validated = $request->validated();

        try {
            $action->approve(
                $subscriptionRequest,
                $request->user(),
                isset($validated['price_paid']) ? (float) $validated['price_paid'] : null,
                $validated['note'] ?? null,
            );
            $this->notifySuccess('notifications.updated', ['resource' => __('resources.subscription_request')]);
        } catch (SubscriptionRequestException|SubscriptionException $e) {
            $this->notifyError($e->getMessage());
        }

        return back();
    }

    public function reject(
        RejectSubscriptionRequestRequest $request,
        int $id,
        ReviewSubscriptionRequestAction $action,
    ): RedirectResponse {
        $subscriptionRequest = $this->requests->findOrFail($id);

        try {
            $action->reject($subscriptionRequest, $request->user(), $request->validated('rejection_reason'));
            $this->notifySuccess('notifications.updated', ['resource' => __('resources.subscription_request')]);
        } catch (SubscriptionRequestException $e) {
            $this->notifyError($e->getMessage());
        }

        return back();
    }
}
