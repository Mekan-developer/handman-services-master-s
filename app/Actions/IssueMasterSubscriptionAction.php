<?php

namespace App\Actions;

use App\Actions\Concerns\SyncsMasterAccess;
use App\Enums\SubscriptionStatus;
use App\Exceptions\SubscriptionException;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Repositories\MasterRepository;
use App\Repositories\MasterSubscriptionRepository;
use Illuminate\Support\Facades\DB;

class IssueMasterSubscriptionAction
{
    use SyncsMasterAccess;

    public function __construct(
        private readonly MasterSubscriptionRepository $subscriptions,
        private readonly MasterRepository $masters,
    ) {}

    /**
     * Sell a subscription to a master.
     *
     * With no running subscription the new one starts immediately (Active).
     * With one already running this is a renewal: the new subscription is queued
     * (Pending) starting the moment the current one ends, so paid-for days are
     * never burned and the "one Active subscription" invariant holds.
     *
     * @param  float|null  $pricePaid  Overrides the plan price — 0 issues free access.
     *
     * @throws SubscriptionException
     */
    public function handle(
        Master $master,
        SubscriptionPlan $plan,
        ?User $issuedBy = null,
        ?float $pricePaid = null,
        ?string $note = null,
    ): MasterSubscription {
        if ($plan->trashed()) {
            throw SubscriptionException::planDeleted();
        }

        if (! $plan->is_active) {
            throw SubscriptionException::planNotAvailable();
        }

        $running = $this->subscriptions->activeForMaster($master);

        $startsAt = $running !== null && $running->expires_at !== null && $running->expires_at->isFuture()
            ? $running->expires_at->copy()
            : now();

        $status = $running !== null && $running->isRunning()
            ? SubscriptionStatus::Pending
            : SubscriptionStatus::Active;

        return DB::transaction(function () use ($master, $plan, $issuedBy, $pricePaid, $note, $startsAt, $status): MasterSubscription {
            $subscription = $this->subscriptions->create([
                'master_id' => $master->id,
                'subscription_plan_id' => $plan->id,
                // Snapshot: later edits to the plan must not rewrite this purchase.
                'plan_name' => $plan->name,
                'price_paid' => $pricePaid ?? (float) $plan->price,
                'duration_days' => $plan->duration_days,
                'status' => $status,
                'starts_at' => $startsAt,
                'expires_at' => $startsAt->copy()->addDays($plan->duration_days),
                'created_by' => $issuedBy?->id,
                'note' => $note,
            ]);

            $this->syncMasterAccess($master, $this->subscriptions, $this->masters);

            return $subscription;
        });
    }
}
