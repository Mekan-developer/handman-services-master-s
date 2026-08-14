<?php

namespace App\Actions;

use App\Models\SubscriptionPlan;
use App\Repositories\SubscriptionPlanRepository;

class ToggleSubscriptionPlanStatusAction
{
    public function __construct(private readonly SubscriptionPlanRepository $repository) {}

    /** Disabling a plan hides it from new purchases; running subscriptions are untouched. */
    public function handle(SubscriptionPlan $plan): SubscriptionPlan
    {
        return $this->repository->toggleStatus($plan);
    }
}
