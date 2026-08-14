<?php

namespace App\Actions;

use App\Models\SubscriptionPlan;
use App\Repositories\SubscriptionPlanRepository;

class UpdateSubscriptionPlanAction
{
    public function __construct(private readonly SubscriptionPlanRepository $repository) {}

    /**
     * Edits only affect future purchases — already sold subscriptions keep their
     * own snapshot of name, price and duration.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        return $this->repository->update($plan, $data);
    }
}
