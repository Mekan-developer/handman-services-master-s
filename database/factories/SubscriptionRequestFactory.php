<?php

namespace Database\Factories;

use App\Enums\SubscriptionRequestStatus;
use App\Models\Client;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionRequest>
 */
class SubscriptionRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionRequestStatus::Pending,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(['client_id' => $client->id]);
    }

    public function forPlan(SubscriptionPlan $plan): static
    {
        return $this->state(['subscription_plan_id' => $plan->id]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => SubscriptionRequestStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(?string $reason = 'Оплата не поступила'): static
    {
        return $this->state([
            'status' => SubscriptionRequestStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);
    }
}
