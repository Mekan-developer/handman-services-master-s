<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Events\MasterLocationUpdated;
use App\Models\City;
use App\Models\Client;
use App\Models\Master;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MasterLocationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function authHeaders(Master $master): array
    {
        return ['Authorization' => 'Bearer '.$master->client->createToken('mobile-client')->plainTextToken];
    }

    public function test_guest_cannot_post_location(): void
    {
        $master = Master::factory()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
        ])->assertUnauthorized();

        $this->assertDatabaseCount('master_locations', 0);
    }

    public function test_master_cannot_post_location_for_another_master(): void
    {
        $master = Master::factory()->create();
        $victim = Master::factory()->create();

        $this->postJson(
            route('api.v1.master.location.store', $victim->id),
            ['latitude' => 37.95, 'longitude' => 58.38],
            $this->authHeaders($master),
        )->assertForbidden();

        $this->assertDatabaseCount('master_locations', 0);
    }

    public function test_active_master_can_post_location(): void
    {
        Event::fake([MasterLocationUpdated::class]);
        $master = Master::factory()->create();

        $response = $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.952,
            'longitude' => 58.382,
        ], $this->authHeaders($master));

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'master_id', 'latitude', 'longitude', 'recorded_at']]);

        $this->assertDatabaseHas('master_locations', [
            'master_id' => $master->id,
            'latitude' => 37.9520000,
            'longitude' => 58.3820000,
        ]);

        Event::assertDispatched(MasterLocationUpdated::class);
    }

    public function test_inactive_master_cannot_post_location(): void
    {
        $master = Master::factory()->inactive()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
        ], $this->authHeaders($master))->assertForbidden();
    }

    public function test_master_with_expired_access_cannot_post_location(): void
    {
        $master = Master::factory()->expired()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
        ], $this->authHeaders($master))->assertForbidden();
    }

    public function test_location_post_validates_coordinates(): void
    {
        $master = Master::factory()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 999,
            'longitude' => 'not-a-number',
        ], $this->authHeaders($master))->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_location_can_be_attached_to_own_active_order(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
            'order_id' => $order->id,
        ], $this->authHeaders($master))->assertCreated();

        $this->assertDatabaseHas('master_locations', [
            'master_id' => $master->id,
            'order_id' => $order->id,
        ]);
    }

    /**
     * The phone is not obliged to name the order. Everything that draws a trail
     * reads `order_id`, so the server binds an untagged ping to the job the
     * master is on rather than storing a point nothing can find.
     */
    public function test_untagged_location_is_bound_to_the_masters_active_order(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->inProgress()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
        ], $this->authHeaders($master))->assertCreated();

        $this->assertDatabaseHas('master_locations', [
            'master_id' => $master->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_untagged_location_stays_untagged_when_the_master_has_no_active_order(): void
    {
        $master = Master::factory()->create();
        Order::factory()->forMaster($master)->completed()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
        ], $this->authHeaders($master))->assertCreated();

        $this->assertDatabaseHas('master_locations', [
            'master_id' => $master->id,
            'order_id' => null,
        ]);
    }

    /**
     * Two open jobs, no way to tell which one the master is driving to. Guessing
     * would put them on the wrong client's map, so the ping stays untagged.
     */
    public function test_untagged_location_stays_untagged_when_several_orders_are_open(): void
    {
        $master = Master::factory()->create();
        Order::factory()->forMaster($master)->assigned()->create();
        Order::factory()->forMaster($master)->inProgress()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
        ], $this->authHeaders($master))->assertCreated();

        $this->assertDatabaseHas('master_locations', [
            'master_id' => $master->id,
            'order_id' => null,
        ]);
    }

    /**
     * The tag decides whose private channel the ping lands on, so a foreign
     * order id must never be accepted — it would stream this master's position
     * to a stranger's map.
     */
    public function test_master_cannot_attach_location_to_another_masters_order(): void
    {
        $master = Master::factory()->create();
        $foreignOrder = Order::factory()->forMaster(Master::factory()->create())->assigned()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
            'order_id' => $foreignOrder->id,
        ], $this->authHeaders($master))->assertNotFound();

        $this->assertDatabaseCount('master_locations', 0);
    }

    public function test_master_cannot_attach_location_to_a_finished_order(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->completed()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
            'order_id' => $order->id,
        ], $this->authHeaders($master))->assertUnprocessable();

        $this->assertDatabaseCount('master_locations', 0);
    }

    public function test_event_carries_correct_payload(): void
    {
        Event::fake([MasterLocationUpdated::class]);
        $master = Master::factory()->create();

        $this->postJson(route('api.v1.master.location.store', $master->id), [
            'latitude' => 37.95,
            'longitude' => 58.38,
        ], $this->authHeaders($master))->assertCreated();

        Event::assertDispatched(MasterLocationUpdated::class, function ($event) use ($master) {
            return $event->location->master_id === $master->id
                && (float) $event->location->latitude === 37.95;
        });
    }

    public function test_event_broadcasts_on_correct_city_channel(): void
    {
        $master = Master::factory()->create();
        $location = $master->locations()->create([
            'latitude' => 37.95,
            'longitude' => 58.38,
            'recorded_at' => now(),
        ]);

        $event = new MasterLocationUpdated($location);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('private-masters-map.'.$master->city_id, $channels[0]->name);
    }

    /**
     * A GPS trail is personal data and the city id is guessable, so the map
     * channel is staff-only.
     */
    public function test_only_admin_staff_may_subscribe_to_the_city_map_channel(): void
    {
        // The test env uses the null broadcaster, which authorizes everything.
        // Swap in a real one and re-register the channels on it, so the
        // authorization callback actually decides.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $city = City::factory()->create();
        $payload = ['channel_name' => 'private-masters-map.'.$city->id, 'socket_id' => '123.456'];

        $this->actingAs(User::factory()->manager()->create())
            ->postJson('/broadcasting/auth', $payload)
            ->assertOk();

        $this->actingAs(User::factory()->operator()->create())
            ->postJson('/broadcasting/auth', $payload)
            ->assertForbidden();

        // A client's mobile token must not open the staff map either.
        $this->postJson('/broadcasting/auth', $payload, $this->authHeaders(Master::factory()->create()))
            ->assertForbidden();
    }

    public function test_event_also_broadcasts_on_the_clients_private_channel_while_tracking(): void
    {
        $client = Client::factory()->create();
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->create(['client_id' => $client->id]);

        $location = $master->locations()->create([
            'latitude' => 37.95,
            'longitude' => 58.38,
            'order_id' => $order->id,
            'recorded_at' => now(),
        ]);

        $channels = (new MasterLocationUpdated($location))->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals('private-masters-map.'.$master->city_id, $channels[0]->name);
        $this->assertEquals('private-client.'.$client->id, $channels[1]->name);
    }

    /** Finishing the job is the stop switch: the client channel drops off the list. */
    public function test_event_stops_reaching_the_client_once_the_order_is_final(): void
    {
        $client = Client::factory()->create();
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->create(['client_id' => $client->id]);

        $location = $master->locations()->create([
            'latitude' => 37.95,
            'longitude' => 58.38,
            'order_id' => $order->id,
            'recorded_at' => now(),
        ]);

        $order->update(['status' => OrderStatus::Completed]);

        $channels = (new MasterLocationUpdated($location->fresh()))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('private-masters-map.'.$master->city_id, $channels[0]->name);
    }

    public function test_event_broadcast_payload_shape(): void
    {
        $master = Master::factory()->create();
        $location = $master->locations()->create([
            'latitude' => 37.95,
            'longitude' => 58.38,
            'recorded_at' => now(),
        ]);

        $payload = (new MasterLocationUpdated($location))->broadcastWith();

        $this->assertEquals([
            'master_id', 'order_id', 'latitude', 'longitude', 'distance_km', 'recorded_at',
        ], array_keys($payload));
        $this->assertNull($payload['distance_km']);
        $this->assertEquals('master.location.updated', (new MasterLocationUpdated($location))->broadcastAs());
    }

    public function test_event_payload_carries_the_distance_left_to_the_client(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->at(38.00, 58.30)->create([
            'client_id' => Client::factory()->create()->id,
        ]);

        $location = $master->locations()->create([
            'latitude' => 37.95,
            'longitude' => 58.30,
            'order_id' => $order->id,
            'recorded_at' => now(),
        ]);

        $payload = (new MasterLocationUpdated($location))->broadcastWith();

        // 0.05° of latitude ≈ 5.57 km.
        $this->assertEqualsWithDelta(5.57, $payload['distance_km'], 0.05);
    }
}
