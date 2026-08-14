<?php

namespace App\Repositories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionPlanRepository
{
    /** Every plan, including disabled ones — admin listing. */
    public function all(): Collection
    {
        return SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->orderBy('duration_days')
            ->get();
    }

    /** Plans a new subscription may be issued from — mobile price list and admin pickers. */
    public function active(): Collection
    {
        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('duration_days')
            ->get();
    }

    public function findOrFail(int $id): SubscriptionPlan
    {
        return SubscriptionPlan::findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        $plan->update($data);

        return $plan->refresh();
    }

    public function toggleStatus(SubscriptionPlan $plan): SubscriptionPlan
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return $plan->refresh();
    }

    /** Soft delete — already purchased subscriptions keep pointing at the plan. */
    public function delete(SubscriptionPlan $plan): void
    {
        $plan->delete();
    }
}
