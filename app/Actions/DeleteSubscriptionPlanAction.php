<?php

namespace App\Actions;

use App\Models\SubscriptionPlan;
use App\Repositories\SubscriptionPlanRepository;

class DeleteSubscriptionPlanAction
{
    public function __construct(private readonly SubscriptionPlanRepository $repository) {}

    /** Soft delete — purchased subscriptions keep working and keep their history. */
    public function handle(SubscriptionPlan $plan): void
    {
        $this->repository->delete($plan);
    }
}
