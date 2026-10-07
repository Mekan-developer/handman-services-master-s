<?php

namespace Database\Factories;

use App\Enums\DevicePlatform;
use App\Models\Client;
use App\Models\ClientDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientDevice>
 */
class ClientDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'token' => fake()->unique()->regexify('[A-Za-z0-9_-]{22}:APA91b[A-Za-z0-9_-]{134}'),
            'platform' => fake()->randomElement(DevicePlatform::cases()),
            'locale' => 'ru',
        ];
    }
}
