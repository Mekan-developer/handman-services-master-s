<?php

namespace App\Actions;

use App\Enums\MasterStatus;
use App\Exceptions\MasterApplicationException;
use App\Models\Master;
use App\Models\User;
use App\Repositories\MasterRepository;
use App\Repositories\SubscriptionPlanRepository;
use Illuminate\Support\Facades\DB;

/**
 * The administrator's verdict on a master application.
 *
 * Approving flips the status and issues the subscription in one go — that
 * mirrors how it works in practice: the master pays the owner in person and
 * the owner opens the account and dials in the paid-for interval right there.
 */
class ReviewMasterApplicationAction
{
    public function __construct(
        private readonly MasterRepository $masters,
        private readonly SubscriptionPlanRepository $plans,
        private readonly IssueMasterSubscriptionAction $issueSubscription,
    ) {}

    /**
     * @param  array{subscription_plan_id: int, subscription_price?: float|null, subscription_note?: string|null}  $subscription
     */
    public function approve(Master $master, User $reviewer, array $subscription): Master
    {
        $this->ensureReviewable($master);

        return DB::transaction(function () use ($master, $reviewer, $subscription): Master {
            $approved = $this->masters->review($master, MasterStatus::Approved, $reviewer);

            $price = $subscription['subscription_price'] ?? null;

            $this->issueSubscription->handle(
                $approved,
                $this->plans->findOrFail((int) $subscription['subscription_plan_id']),
                $reviewer,
                $price !== null ? (float) $price : null,
                $subscription['subscription_note'] ?? null,
            );

            return $approved->refresh();
        });
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
