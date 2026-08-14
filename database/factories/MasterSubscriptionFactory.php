<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MasterSubscription>
 */
class MasterSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plan = SubscriptionPlan::factory();

        return [
            'master_id' => Master::factory(),
            'subscription_plan_id' => $plan,
            'plan_name' => 'Тариф на 30 дней',
            'price_paid' => 150,
            'duration_days' => 30,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'created_by' => null,
            'note' => null,
        ];
    }

    public function forMaster(Master $master): static
    {
        return $this->state(['master_id' => $master->id]);
    }

    public function fromPlan(SubscriptionPlan $plan): static
    {
        return $this->state([
            'subscription_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'price_paid' => $plan->price,
            'duration_days' => $plan->duration_days,
            'expires_at' => now()->addDays($plan->duration_days),
        ]);
    }

    public function pending(): static
    {
        return $this->state(['status' => SubscriptionStatus::Pending]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Expired,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDays(30),
        ]);
    }

    /** Still flagged Active but already past its end date — what the expire command picks up. */
    public function overdue(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => SubscriptionStatus::Cancelled]);
    }
}
