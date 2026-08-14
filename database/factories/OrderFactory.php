<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Master;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'city_id' => fn () => City::query()->inRandomOrder()->value('id') ?? City::factory(),
            'category_id' => fn () => Category::query()
                ->whereNotNull('parent_id')
                ->inRandomOrder()
                ->value('id') ?? Category::factory(),
            'master_id' => null,
            'status' => OrderStatus::Pending,
            'client_name' => fake()->name(),
            'client_phone' => fake()->numerify('+99362#######'),
            'description' => fake()->sentence(12),
            'client_address' => fake()->streetAddress(),
            'client_lat' => fake()->randomFloat(7, 37.9, 38.1),
            'client_lng' => fake()->randomFloat(7, 58.3, 58.5),
            'final_price' => null,
        ];
    }

    public function forCity(City $city, float $lat, float $lng): static
    {
        return $this->state([
            'city_id' => $city->id,
            'client_lat' => $lat + fake()->randomFloat(4, -0.05, 0.05),
            'client_lng' => $lng + fake()->randomFloat(4, -0.05, 0.05),
        ]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(['category_id' => $category->id]);
    }

    /** Pins the client location, so distance-based tests are deterministic. */
    public function at(float $latitude, float $longitude): static
    {
        return $this->state([
            'client_lat' => $latitude,
            'client_lng' => $longitude,
        ]);
    }

    /** Auto-search running: pending, unclaimed, at the given radius. */
    public function searching(int $radiusKm = 20, ?CarbonInterface $startedAt = null): static
    {
        return $this->state([
            'status' => OrderStatus::Pending,
            'master_id' => null,
            'search_started_at' => $startedAt ?? now(),
            'search_radius_km' => $radiusKm,
            'search_expired_at' => null,
        ]);
    }

    /** Auto-search gave up — waiting for an administrator to assign a master. */
    public function searchExpired(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => OrderStatus::Pending,
            'master_id' => null,
            'search_started_at' => $attrs['search_started_at'] ?? now()->subMinutes(10),
            'search_expired_at' => now(),
        ]);
    }

    public function forMaster(Master $master): static
    {
        return $this->state([
            'master_id' => $master->id,
            'city_id' => $master->city_id,
        ]);
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => OrderStatus::Assigned,
            'master_id' => $attrs['master_id'] ?? Master::query()->inRandomOrder()->value('id'),
            'assigned_at' => now()->subMinutes(rand(5, 120)),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => OrderStatus::InProgress,
            'master_id' => $attrs['master_id'] ?? Master::query()->inRandomOrder()->value('id'),
            'assigned_at' => now()->subHours(2),
            'started_at' => now()->subMinutes(rand(10, 90)),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => OrderStatus::Completed,
            'master_id' => $attrs['master_id'] ?? Master::query()->inRandomOrder()->value('id'),
            'assigned_at' => now()->subHours(rand(5, 48)),
            'started_at' => now()->subHours(rand(3, 24)),
            'completed_at' => now()->subHours(rand(1, 12)),
            'final_price' => fake()->randomFloat(2, 100, 1500),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now()->subHours(rand(1, 48)),
            'cancel_reason' => fake()->randomElement([
                'Клиент передумал',
                'Не дозвонились до клиента',
                'Нет свободных мастеров',
                'Дубликат заявки',
            ]),
        ]);
    }
}
