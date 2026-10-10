<?php

namespace App\Actions;

use App\Enums\MasterStatus;
use App\Enums\SubscriptionRequestStatus;
use App\Exceptions\MasterApplicationException;
use App\Models\Master;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Notifications\Push\SubscriptionRequestApprovedNotification;
use App\Repositories\MasterRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionRequestRepository;
use Illuminate\Support\Facades\DB;

/**
 * The administrator's verdict on a master application.
 *
 * Approving flips the status and issues the subscription in one go — that
 * mirrors how it works in practice: the master pays the owner in person and
 * the owner opens the account and dials in the paid-for interval right there.
 *
 * The plan the applicant requested from the app is fulfilled by that same
 * subscription, so the pending request is closed here instead of lingering in
 * the subscription-requests queue where approving it would sell a second one.
 */
class ReviewMasterApplicationAction
{
    public function __construct(
        private readonly MasterRepository $masters,
        private readonly SubscriptionPlanRepository $plans,
        private readonly SubscriptionRequestRepository $requests,
        private readonly IssueMasterSubscriptionAction $issueSubscription,
    ) {}

    /**
     * @param  array{subscription_plan_id: int, subscription_price?: float|null, subscription_note?: string|null}  $subscription
     */
    public function approve(Master $master, User $reviewer, array $subscription): Master
    {
        $this->ensureReviewable($master);

        /** @var array{0: Master, 1: SubscriptionRequest|null} $result */
        $result = DB::transaction(function () use ($master, $reviewer, $subscription): array {
            $approved = $this->masters->review($master, MasterStatus::Approved, $reviewer);

            $price = $subscription['subscription_price'] ?? null;

            $issued = $this->issueSubscription->handle(
                $approved,
                $this->plans->findOrFail((int) $subscription['subscription_plan_id']),
                $reviewer,
                $price !== null ? (float) $price : null,
                $subscription['subscription_note'] ?? null,
            );

            $pendingRequest = $this->requests->pendingForClient($approved->client);

            $closedRequest = $pendingRequest !== null
                ? $this->requests->review($pendingRequest, SubscriptionRequestStatus::Approved, $reviewer, subscription: $issued)
                : null;

            return [$approved->refresh(), $closedRequest];
        });

        [$approved, $closedRequest] = $result;

        // After commit: the push describes a subscription that exists.
        $closedRequest?->client->notify(new SubscriptionRequestApprovedNotification($closedRequest->load('subscription')));

        return $approved;
    }

    public function reject(Master $master, User $reviewer, string $reason): Master
    {
        $this->ensureReviewable($master);

        return $this->masters->review($master, MasterStatus::Rejected, $reviewer, $reason);
    }

    /** @throws MasterApplicationException */
    private function ensureReviewable(Master $master): void
    {
        if ($master->status === MasterStatus::Approved) {
            throw MasterApplicationException::alreadyApproved();
        }

        if (! $master->isPending()) {
            throw MasterApplicationException::notUnderReview();
        }
    }
}
