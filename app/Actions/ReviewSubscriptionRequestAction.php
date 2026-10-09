<?php

namespace App\Actions;

use App\Enums\SubscriptionRequestStatus;
use App\Exceptions\SubscriptionException;
use App\Exceptions\SubscriptionRequestException;
use App\Models\Master;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Notifications\Push\SubscriptionRequestApprovedNotification;
use App\Notifications\Push\SubscriptionRequestRejectedNotification;
use App\Repositories\MasterRepository;
use App\Repositories\SubscriptionRequestRepository;
use Illuminate\Support\Facades\DB;

/**
 * The administrator's verdict on a subscription request from the app.
 *
 * Approving sells the requested plan through {@see IssueMasterSubscriptionAction},
 * so renewals queue behind a running subscription and `access_expires_at` is
 * re-derived exactly as for a subscription issued by hand.
 */
class ReviewSubscriptionRequestAction
{
    public function __construct(
        private readonly SubscriptionRequestRepository $requests,
        private readonly MasterRepository $masters,
        private readonly IssueMasterSubscriptionAction $issueSubscription,
    ) {}

    /**
     * @param  float|null  $pricePaid  Overrides the plan price — what was actually taken.
     *
     * @throws SubscriptionRequestException
     * @throws SubscriptionException
     */
    public function approve(
        SubscriptionRequest $request,
        User $reviewer,
        ?float $pricePaid = null,
        ?string $note = null,
    ): SubscriptionRequest {
        $this->ensurePending($request);

        // Soft-deleted plans still load; only a force-deleted one leaves nothing to sell.
        if ($request->plan === null) {
            throw SubscriptionException::planDeleted();
        }

        $master = $this->masters->findByClient($request->client);

        if (! $master instanceof Master || ! $master->isApproved()) {
            throw SubscriptionRequestException::notAMaster();
        }

        $approved = DB::transaction(function () use ($request, $reviewer, $master, $pricePaid, $note): SubscriptionRequest {
            $subscription = $this->issueSubscription->handle($master, $request->plan, $reviewer, $pricePaid, $note);

            return $this->requests->review($request, SubscriptionRequestStatus::Approved, $reviewer, subscription: $subscription);
        });

        $request->client->notify(new SubscriptionRequestApprovedNotification($approved->load('subscription')));

        return $approved;
    }

    /** @throws SubscriptionRequestException */
    public function reject(SubscriptionRequest $request, User $reviewer, ?string $reason = null): SubscriptionRequest
    {
        $this->ensurePending($request);

        $rejected = $this->requests->review($request, SubscriptionRequestStatus::Rejected, $reviewer, $reason);

        $request->client->notify(new SubscriptionRequestRejectedNotification($rejected));

        return $rejected;
    }

    /** @throws SubscriptionRequestException */
    private function ensurePending(SubscriptionRequest $request): void
    {
        if (! $request->isPending()) {
            throw SubscriptionRequestException::notPending();
        }
    }
}
