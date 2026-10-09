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
     * With no running subscription a new one starts immediately (Active).
     * With one still running this is a renewal: the bought days are added to
     * that subscription's end date on the spot, so the master keeps a single
     * subscription that simply lasts longer — nothing waits in a queue.
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

        $price = $pricePaid ?? (float) $plan->price;

        return DB::transaction(function () use ($master, $plan, $issuedBy, $price, $note): MasterSubscription {
            $running = $this->subscriptions->renewableForMaster($master);

            // Past its end but not yet swept by `subscriptions:expire` — retire it
            // now so the fresh one never sits next to a second Active row.
            if ($running === null && ($overdue = $this->subscriptions->activeForMaster($master)) !== null) {
                $this->subscriptions->updateStatus($overdue, SubscriptionStatus::Expired);
            }

            $subscription = $running !== null
                ? $this->extend($running, $plan, $price, $note)
                : $this->subscriptions->create([
                    'master_id' => $master->id,
                    'subscription_plan_id' => $plan->id,
                    // Snapshot: later edits to the plan must not rewrite this purchase.
                    'plan_name' => $plan->name,
                    'price_paid' => $this->money($price),
                    'duration_days' => $plan->duration_days,
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($plan->duration_days),
                    'created_by' => $issuedBy?->id,
                    'note' => $note,
                ]);

            $this->syncMasterAccess($master, $this->subscriptions, $this->masters);

            return $subscription;
        });
    }

    /**
     * Fold a renewal into the running subscription: its end date, length and
     * amount grow by what was just bought, and it now reads as the latest plan.
     */
    private function extend(MasterSubscription $running, SubscriptionPlan $plan, float $price, ?string $note): MasterSubscription
    {
        return $this->subscriptions->update($running, [
            'subscription_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'price_paid' => $this->money((float) $running->price_paid + $price),
            'duration_days' => $running->duration_days + $plan->duration_days,
            'expires_at' => $running->expires_at->copy()->addDays($plan->duration_days),
            'note' => collect([$running->note, $note])->filter()->implode('; ') ?: null,
            // The end date moved, so the "expires tomorrow" push is due again.
            'expiry_reminded_at' => null,
        ]);
    }

    /** The `decimal:2` cast expects a string — floats trigger brick/math deprecations. */
    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
