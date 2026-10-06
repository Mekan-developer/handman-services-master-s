<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\City;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MasterAvailableOrdersTest extends TestCase
{
    use RefreshDatabase;

    /** Ashgabat centre — the reference point every fixture is measured from. */
    private const LAT = 37.9601;

    private const LNG = 58.3261;

    private Master $master;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->child(Category::factory()->create())->create();
        $this->master = Master::factory()->create(['is_available' => true]);
        $this->master->categories()->sync([$this->category->id]);

        $this->pingLocation(self::LAT, self::LNG);
    }

    private function pingLocation(float $latitude, float $longitude): void
    {
        MasterLocation::factory()->create([
            'master_id' => $this->master->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => now(),
        ]);
    }

    /** A point $km north of the reference coordinates. */
    private function kmNorth(float $km): float
    {
        return self::LAT + $km / Order::KM_PER_LAT_DEGREE;
    }

    private function available(): TestResponse
    {
        return $this->withToken($this->master->client->createToken('mobile-client')->plainTextToken)
            ->getJson(route('api.v1.master.orders.available'));
    }

    public function test_master_sees_a_pending_order_inside_the_current_radius(): void
    {
        $order = Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(10), self::LNG)
            ->searching(20)
            ->create();

        $response = $this->available();

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($order->id, $response->json('data.0.id'));
        $this->assertEqualsWithDelta(10.0, $response->json('data.0.distance_km'), 0.2);
    }

    public function test_order_beyond_the_current_radius_is_hidden(): void
    {
        Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(35), self::LNG)
            ->searching(20)
            ->create();

        $this->available()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_same_order_appears_once_the_radius_has_grown_past_it(): void
    {
        $order = Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(35), self::LNG)
            ->searching(20)
            ->create();

        $this->available()->assertJsonCount(0, 'data');

        $order->update(['search_radius_km' => 40]);

        $this->available()->assertJsonCount(1, 'data');
    }

    public function test_order_from_another_category_is_hidden(): void
    {
        Order::factory()
            ->forCategory(Category::factory()->child(Category::factory()->create())->create())
            ->at($this->kmNorth(5), self::LNG)
            ->searching(20)
            ->create();

        $this->available()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_already_assigned_order_is_hidden(): void
    {
        $other = Master::factory()->create();

        Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(5), self::LNG)
            ->searching(20)
            ->assigned()
            ->create(['master_id' => $other->id]);

        $this->available()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_order_whose_search_expired_is_hidden(): void
    {
        Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(5), self::LNG)
            ->searching(80)
            ->searchExpired()
            ->create();

        $this->available()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_master_does_not_see_an_order_placed_from_their_own_client_account(): void
    {
        Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(5), self::LNG)
            ->searching(20)
            ->create(['client_id' => $this->master->client_id]);

        $this->available()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_master_still_sees_orders_placed_by_other_clients(): void
    {
        $other = Master::factory()->create();

        $order = Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(5), self::LNG)
            ->searching(20)
            ->create(['client_id' => $other->client_id]);

        $response = $this->available()->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($order->id, $response->json('data.0.id'));
    }

    public function test_orders_are_visible_across_city_borders(): void
    {
        Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(15), self::LNG)
            ->searching(20)
            ->create(['city_id' => City::factory()->create()->id]);

        $this->available()->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_orders_are_sorted_nearest_first(): void
    {
        $far = Order::factory()->forCategory($this->category)->at($this->kmNorth(18), self::LNG)->searching(20)->create();
        $near = Order::factory()->forCategory($this->category)->at($this->kmNorth(3), self::LNG)->searching(20)->create();
        $mid = Order::factory()->forCategory($this->category)->at($this->kmNorth(9), self::LNG)->searching(20)->create();

        $ids = $this->available()->assertOk()->json('data.*.id');

        $this->assertSame([$near->id, $mid->id, $far->id], $ids);
    }

    public function test_master_without_a_location_ping_gets_an_empty_list(): void
    {
        $stranded = Master::factory()->create();
        $stranded->categories()->sync([$this->category->id]);

        Order::factory()->forCategory($this->category)->at(self::LAT, self::LNG)->searching(20)->create();

        $this->withToken($stranded->client->createToken('mobile-client')->plainTextToken)
            ->getJson(route('api.v1.master.orders.available'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_feed_shows_the_client_name_and_phone(): void
    {
        Order::factory()
            ->forCategory($this->category)
            ->at($this->kmNorth(5), self::LNG)
            ->searching(20)
            ->create(['client_name' => 'Гурбан Гурбанов', 'client_phone' => '+99362999999']);

        $payload = $this->available()->assertOk()->json('data.0');

        $this->assertSame('Гурбан Гурбанов', $payload['client_name']);
        $this->assertSame('+99362999999', $payload['client_phone']);
    }

    public function test_guest_cannot_read_the_feed(): void
    {
        $this->getJson(route('api.v1.master.orders.available'))->assertUnauthorized();
    }
}
