<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Events\OrderSearchStarted;
use App\Models\Category;
use App\Models\City;
use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CreateOrderStartsSearchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $parent = Category::factory()->create();

        return [
            'city_id' => City::factory()->create()->id,
            'category_id' => Category::factory()->child($parent)->create()->id,
            'description' => 'Протекает кран на кухне',
            'client_phone' => '+99362123456',
            'client_address' => 'ул. Огузхана 12',
            'client_lat' => 37.95,
            'client_lng' => 58.38,
        ];
    }

    private function tokenFor(Client $client): string
    {
        return $client->createToken('mobile-client')->plainTextToken;
    }

    public function test_client_order_starts_the_search_at_the_configured_initial_radius(): void
    {
        Setting::create(['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => '15']);
        $client = Client::factory()->create();

        $response = $this->withToken($this->tokenFor($client))
            ->postJson(route('api.v1.client.orders.store'), $this->payload());

        $response->assertCreated();

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'search_radius_km' => 15,
            'search_expired_at' => null,
        ]);

        $this->assertNotNull(Order::find($response->json('data.id'))->search_started_at);
    }

    public function test_search_falls_back_to_the_default_radius_when_unset(): void
    {
        $client = Client::factory()->create();

        $response = $this->withToken($this->tokenFor($client))
            ->postJson(route('api.v1.client.orders.store'), $this->payload());

        $response->assertCreated();

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'search_radius_km' => Setting::DEFAULT_SEARCH_INITIAL_RADIUS_KM,
        ]);
    }

    public function test_creation_signals_the_master_apps_without_leaking_the_client(): void
    {
        Event::fake([OrderSearchStarted::class, OrderCreated::class]);

        $client = Client::factory()->create();

        $this->withToken($this->tokenFor($client))
            ->postJson(route('api.v1.client.orders.store'), $this->payload())
            ->assertCreated();

        Event::assertDispatched(OrderSearchStarted::class, function (OrderSearchStarted $event) {
            $payload = $event->broadcastWith();

            $this->assertSame(['available-orders'], array_map(
                fn ($channel) => $channel->name,
                $event->broadcastOn(),
            ));
            $this->assertSame(['order_id', 'radius_km'], array_keys($payload));

            return true;
        });

        // The admin-facing event carries the client's name, so it must stay off
        // the public master channel.
        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) {
            $this->assertSame(['orders'], array_map(
                fn ($channel) => $channel->name,
                $event->broadcastOn(),
            ));

            return true;
        });
    }

    public function test_blank_radius_setting_falls_back_to_the_default(): void
    {
        Setting::create(['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => '']);
        $client = Client::factory()->create();

        $response = $this->withToken($this->tokenFor($client))
            ->postJson(route('api.v1.client.orders.store'), $this->payload());

        $response->assertCreated();

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'search_radius_km' => Setting::DEFAULT_SEARCH_INITIAL_RADIUS_KM,
        ]);
    }
}
