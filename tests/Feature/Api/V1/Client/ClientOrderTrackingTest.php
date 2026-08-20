<?php

namespace Tests\Feature\Api\V1\Client;

use App\Models\Client;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientOrderTrackingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsClient(): Client
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client, ['*']);

        return $client;
    }

    /** Drops `count` pings along the order, one minute apart, oldest first. */
    private function trail(Order $order, int $count = 3): void
    {
        foreach (range(1, $count) as $minutesAgo) {
            MasterLocation::factory()->create([
                'master_id' => $order->master_id,
                'order_id' => $order->id,
                'latitude' => 37.90 + ($minutesAgo / 100),
                'longitude' => 58.30,
                'recorded_at' => now()->subMinutes($count - $minutesAgo + 1),
            ]);
        }
    }

    public function test_guest_cannot_read_a_track(): void
    {
        $order = Order::factory()->assigned()->create();

        $this->getJson(route('api.v1.client.orders.track', $order))->assertUnauthorized();
    }

    public function test_client_cannot_read_someone_elses_track(): void
    {
        $this->actingAsClient();
        $stranger = Client::factory()->create();
        $order = Order::factory()->assigned()->create(['client_id' => $stranger->id]);

        $this->getJson(route('api.v1.client.orders.track', $order))->assertNotFound();
    }

    public function test_client_gets_the_masters_trail_for_an_assigned_order(): void
    {
        $client = $this->actingAsClient();
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->at(38.00, 58.30)->create([
            'client_id' => $client->id,
        ]);
        $this->trail($order);

        $response = $this->getJson(route('api.v1.client.orders.track', $order))
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.master.id', $master->id)
            ->assertJsonCount(3, 'data.points')
            ->assertJsonStructure(['data' => [
                'order_id', 'status', 'is_active',
                'destination' => ['latitude', 'longitude', 'address'],
                'master' => ['id', 'name', 'phone'],
                'last_location' => ['latitude', 'longitude', 'recorded_at', 'distance_km'],
                'points' => [['latitude', 'longitude', 'recorded_at']],
            ]]);

        // Newest ping sits at 37.93, i.e. ~7.8 km south of the client at 38.00.
        $this->assertEqualsWithDelta(7.8, $response->json('data.last_location.distance_km'), 0.3);
    }

    public function test_points_come_back_oldest_first(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->forMaster(Master::factory()->create())->assigned()->create([
            'client_id' => $client->id,
        ]);
        $this->trail($order);

        $points = $this->getJson(route('api.v1.client.orders.track', $order))->json('data.points');
        $timestamps = array_column($points, 'recorded_at');

        $sorted = $timestamps;
        sort($sorted);
        $this->assertSame($sorted, $timestamps);
    }

    public function test_since_returns_only_the_missing_tail(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->forMaster(Master::factory()->create())->assigned()->create([
            'client_id' => $client->id,
        ]);
        $this->trail($order, 4);

        $cutoff = now()->subMinutes(3);

        $this->getJson(route('api.v1.client.orders.track', ['order' => $order->id, 'since' => $cutoff->toIso8601String()]))
            ->assertOk()
            ->assertJsonCount(2, 'data.points');
    }

    public function test_since_must_be_a_date(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->assigned()->create(['client_id' => $client->id]);

        $this->getJson(route('api.v1.client.orders.track', ['order' => $order->id, 'since' => 'yesterday-ish']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['since']);
    }

    public function test_tracking_is_active_while_the_job_is_in_progress(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->forMaster(Master::factory()->create())->inProgress()->create([
            'client_id' => $client->id,
        ]);

        $this->getJson(route('api.v1.client.orders.track', $order))
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_completed_order_stops_tracking_and_hides_the_trail(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->forMaster(Master::factory()->create())->completed()->create([
            'client_id' => $client->id,
        ]);
        $this->trail($order);

        $this->getJson(route('api.v1.client.orders.track', $order))
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.last_location', null)
            ->assertJsonCount(0, 'data.points');
    }

    public function test_cancelled_order_stops_tracking(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->forMaster(Master::factory()->create())->cancelled()->create([
            'client_id' => $client->id,
        ]);
        $this->trail($order);

        $this->getJson(route('api.v1.client.orders.track', $order))
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonCount(0, 'data.points');
    }

    public function test_pending_order_without_a_master_is_not_trackable(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);

        $this->getJson(route('api.v1.client.orders.track', $order))
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.master', null);
    }

    public function test_trail_of_another_order_does_not_leak_into_this_one(): void
    {
        $client = $this->actingAsClient();
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->create(['client_id' => $client->id]);
        $otherOrder = Order::factory()->forMaster($master)->assigned()->create();

        $this->trail($order, 2);
        $this->trail($otherOrder, 5);

        $this->getJson(route('api.v1.client.orders.track', $order))
            ->assertOk()
            ->assertJsonCount(2, 'data.points');
    }
}
