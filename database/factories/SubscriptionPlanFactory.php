<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $days = fake()->randomElement([30, 90, 180]);

        return [
            'name_ru' => "Тариф на {$days} дней",
            'name_tk' => "{$days} günlük nyrh",
            'description_ru' => fake()->optional()->sentence(),
            'description_tk' => fake()->optional()->sentence(),
            'duration_days' => $days,
            'price' => fake()->randomFloat(2, 50, 500),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function days(int $days): static
    {
        return $this->state(['duration_days' => $days]);
    }
}
