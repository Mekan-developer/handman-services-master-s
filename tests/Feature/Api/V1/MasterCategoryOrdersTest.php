<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderResponseStatus;
use App\Models\Category;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use App\Models\OrderMasterDecline;
use App\Models\OrderMasterResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MasterCategoryOrdersTest extends TestCase
{
    use RefreshDatabase;

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
    }

    private function pingLocation(): void
    {
        MasterLocation::factory()->create([
            'master_id' => $this->master->id,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'recorded_at' => now(),
        ]);
    }

    private function kmNorth(float $km): float
    {
        return self::LAT + $km / Order::KM_PER_LAT_DEGREE;
    }

    /** @param array<string, mixed> $query */
    private function byCategory(array $query = []): TestResponse
    {
        return $this->withToken($this->master->client->createToken('mobile-client')->plainTextToken)
            ->getJson(route('api.v1.master.orders.by-category', $query));
    }

    public function test_master_sees_orders_of_their_category_outside_the_radius(): void
    {
        $this->pingLocation();

        $far = Order::factory()->forCategory($this->category)->at($this->kmNorth(100), self::LNG)->searching(20)->create();

        $response = $this->byCategory()->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($far->id, $response->json('data.0.id'));
        $this->assertEqualsWithDelta(100.0, $response->json('data.0.distance_km'), 0.5);
        $this->assertFalse($response->json('data.0.is_within_radius'));
    }

    public function test_order_inside_the_radius_is_flagged_as_respondable(): void
    {
        $this->pingLocation();

        Order::factory()->forCategory($this->category)->at($this->kmNorth(5), self::LNG)->searching(20)->create();

        $this->byCategory()->assertOk()->assertJsonPath('data.0.is_within_radius', true);
    }

    public function test_master_without_a_location_still_sees_orders_without_distance(): void
    {
        Order::factory()->forCategory($this->category)->at($this->kmNorth(5), self::LNG)->searching(20)->create();

        $this->byCategory()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.distance_km', null)
            ->assertJsonPath('data.0.is_within_radius', false);
    }

    public function test_order_from_another_category_is_hidden(): void
    {
        Order::factory()
            ->forCategory(Category::factory()->child(Category::factory()->create())->create())
            ->at($this->kmNorth(5), self::LNG)
            ->searching(20)
            ->create();

        $this->byCategory()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_assigned_and_own_orders_are_hidden(): void
    {
        $other = Master::factory()->create();

        Order::factory()->forCategory($this->category)->searching(20)->assigned()->create(['master_id' => $other->id]);
        Order::factory()->forCategory($this->category)->searching(20)->create(['client_id' => $this->master->client_id]);

        $this->byCategory()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_order_whose_search_expired_is_still_listed(): void
    {
        $order = Order::factory()->forCategory($this->category)->searching(80)->searchExpired()->create();

        $response = $this->byCategory()->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($order->id, $response->json('data.0.id'));
    }

    public function test_declined_and_already_responded_orders_are_hidden(): void
    {
        $declined = Order::factory()->forCategory($this->category)->searching(20)->create();
        $responded = Order::factory()->forCategory($this->category)->searching(20)->create();

        OrderMasterDecline::create(['order_id' => $declined->id, 'master_id' => $this->master->id]);
        OrderMasterResponse::create([
            'order_id' => $responded->id,
            'master_id' => $this->master->id,
            'status' => OrderResponseStatus::Pending,
        ]);

        $this->byCategory()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_feed_can_be_narrowed_to_one_of_the_masters_categories(): void
    {
        $second = Category::factory()->child(Category::factory()->create())->create();
        $this->master->categories()->attach($second->id);

        Order::factory()->forCategory($this->category)->searching(20)->create();
        $wanted = Order::factory()->forCategory($second)->searching(20)->create();

        $this->byCategory()->assertJsonCount(2, 'data');

        $response = $this->byCategory(['category_id' => $second->id])->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($wanted->id, $response->json('data.0.id'));
    }

    public function test_filtering_by_a_foreign_category_returns_nothing(): void
    {
        $foreign = Category::factory()->child(Category::factory()->create())->create();
        Order::factory()->forCategory($foreign)->searching(20)->create();

        $this->byCategory(['category_id' => $foreign->id])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unknown_category_id_is_rejected(): void
    {
        $this->byCategory(['category_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_feed_is_paginated_and_shows_client_contacts(): void
    {
        Order::factory()->forCategory($this->category)->searching(20)
            ->create(['client_name' => 'Гурбан Гурбанов', 'client_phone' => '+99362999999']);

        $response = $this->byCategory()->assertOk()->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertSame('Гурбан Гурбанов', $response->json('data.0.client_name'));
        $this->assertSame('+99362999999', $response->json('data.0.client_phone'));
    }

    public function test_guest_cannot_read_the_feed(): void
    {
        $this->getJson(route('api.v1.master.orders.by-category'))->assertUnauthorized();
    }
}
