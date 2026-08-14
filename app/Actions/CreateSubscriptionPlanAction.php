<?php

namespace App\Actions;

use App\Models\SubscriptionPlan;
use App\Repositories\SubscriptionPlanRepository;

class CreateSubscriptionPlanAction
{
    public function __construct(private readonly SubscriptionPlanRepository $repository) {}

    /**
     * @param  array{name_ru: string, name_tk: string, description_ru?: string|null, description_tk?: string|null, duration_days: int, price: float|string, is_active: bool, sort_order?: int}  $data
     */
    public function handle(array $data): SubscriptionPlan
    {
        return $this->repository->create($data);
    }
}
