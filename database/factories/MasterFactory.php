<?php

namespace Database\Factories;

use App\Enums\MasterStatus;
use App\Models\City;
use App\Models\Client;
use App\Models\Master;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Master>
 */
class MasterFactory extends Factory
{
    /**
     * Defaults to a working master — approved, active, subscription running —
     * because that is what almost every test needs. Applications in review are
     * built with {@see pending()}.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'city_id' => City::factory(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('+99362#######'),
            'status' => MasterStatus::Approved,
            'experience_years' => fake()->numberBetween(1, 20),
            'about' => fake()->sentence(),
            'reviewed_at' => now(),
            'access_expires_at' => now()->addDays(30),
            'is_active' => true,
            'photo' => null,
        ];
    }

    /** Attach the profile to an existing client account, mirroring its identity. */
    public function forClient(Client $client): static
    {
        return $this->state([
            'client_id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
        ]);
    }

    /** A submitted application waiting for an administrator: no access yet. */
    public function pending(): static
    {
        return $this->state([
            'status' => MasterStatus::Pending,
            'reviewed_at' => null,
            'access_expires_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Not enough experience'): static
    {
        return $this->state([
            'status' => MasterStatus::Rejected,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
            'access_expires_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }

    public function expired(): static
    {
        return $this->state(['access_expires_at' => now()->subDay()]);
    }
}
