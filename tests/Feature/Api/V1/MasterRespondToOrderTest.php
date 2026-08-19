<?php

namespace Tests\Feature\Api\V1;

use App\Actions\RespondToOrderAction;
use App\Enums\OrderResponseStatus;
use App\Enums\OrderStatus;
use App\Events\MasterRespondedToOrder;
use App\Models\Category;
use App\Models\City;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MasterRespondToOrderTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 37.9601;

    private const LNG = 58.3261;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->child(Category::factory()->create())->create();
    }

    private function master(float $latitude = self::LAT, float $longitude = self::LNG, array $attributes = []): Master
    {
        $master = Master::factory()->create($attributes + ['is_available' => true]);
        $master->categories()->sync([$this->category->id]);

        MasterLocation::factory()->create([
            'master_id' => $master->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => now(),
        ]);

        return $master;
    }

    private function order(float $distanceKm = 5, int $radiusKm = 20): Order
    {
        return Order::factory()
            ->forCategory($this->category)
            ->at(self::LAT + $distanceKm / Order::KM_PER_LAT_DEGREE, self::LNG)
            ->searching($radiusKm)
            ->create();
    }

    /**
     * The sanctum guard caches the resolved user for the lifetime of the test,
     * so a race test that switches masters mid-test must drop that cache first —
     * otherwise the second request silently runs as the first master and the
     * assertion passes for the wrong reason.
     */
    private function respond(Master $master, Order $order): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($master->client->createToken('mobile-client')->plainTextToken)
            ->postJson(route('api.v1.master.orders.respond', $order));
    }

    public function test_master_can_respond_to_an_offered_order(): void
    {
        Event::fake([MasterRespondedToOrder::class]);

        $master = $this->master();
        $order = $this->order();

        $this->respond($master, $order)->assertCreated();

        $this->assertDatabaseHas('order_master_responses', [
            'order_id' => $order->id,
            'master_id' => $master->id,
            'status' => OrderResponseStatus::Pending->value,
        ]);

        // The order stays in the pool — responding does not assign it.
        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Pending, $fresh->status);
        $this->assertNull($fresh->master_id);

        Event::assertDispatched(MasterRespondedToOrder::class);
    }

    public function test_multiple_masters_can_respond_to_the_same_order(): void
    {
        $first = $this->master();
        $second = $this->master();
        $order = $this->order();

        $this->respond($first, $order)->assertCreated();
        $this->respond($second, $order->fresh())->assertCreated();

        $this->assertSame(2, OrderMasterResponse::where('order_id', $order->id)->count());
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_a_master_cannot_respond_twice_to_the_same_order(): void
    {
        $master = $this->master();
        $order = $this->order();

        $this->respond($master, $order)->assertCreated();

        $this->respond($master, $order->fresh())
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.already_responded'));

        $this->assertSame(1, OrderMasterResponse::where('order_id', $order->id)->count());
    }

    public function test_master_outside_the_current_radius_is_rejected(): void
    {
        $master = $this->master();
        $order = $this->order(distanceKm: 35, radiusKm: 20);

        $this->respond($master, $order)
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.out_of_search_radius'));

        $this->assertNull($order->fresh()->master_id);
    }

    public function test_master_without_the_order_category_is_rejected(): void
    {
        $master = Master::factory()->create(['is_available' => true]);
        $master->categories()->sync([Category::factory()->child(Category::factory()->create())->create()->id]);
        MasterLocation::factory()->create([
            'master_id' => $master->id,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'recorded_at' => now(),
        ]);

        $this->respond($master, $this->order())
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.category_mismatch'));
    }

    public function test_master_without_a_location_ping_is_rejected(): void
    {
        $master = Master::factory()->create(['is_available' => true]);
        $master->categories()->sync([$this->category->id]);

        $this->respond($master, $this->order())
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.master_location_unknown'));
    }

    public function test_unavailable_master_is_rejected(): void
    {
        $master = $this->master(attributes: ['is_available' => false]);

        $this->respond($master, $this->order())
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.master_unavailable'));
    }

    /** The ensure.master middleware turns expired access away before the action runs. */
    public function test_master_with_expired_access_is_rejected_at_the_middleware(): void
    {
        $master = $this->master(attributes: ['access_expires_at' => now()->subDay()]);

        $this->respond($master, $this->order())
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.master.access_expired'));
    }

    /** …and the action refuses it too, for any caller that bypasses the route. */
    public function test_the_action_itself_refuses_a_master_with_expired_access(): void
    {
        $master = $this->master(attributes: ['access_expires_at' => now()->subDay()]);

        $this->expectExceptionMessage(__('orders.errors.master_inactive'));

        app(RespondToOrderAction::class)->handle($master, $this->order());
    }

    public function test_order_whose_search_expired_can_no_longer_be_responded_to(): void
    {
        $master = $this->master();
        $order = $this->order();
        $order->update(['search_expired_at' => now()]);

        $this->respond($master, $order->fresh())
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.search_expired'));

        $this->assertNull($order->fresh()->master_id);
    }

    public function test_master_can_respond_across_a_city_border(): void
    {
        $master = $this->master();
        $order = $this->order();
        $order->update(['city_id' => City::factory()->create()->id]);

        $this->respond($master, $order->fresh())->assertCreated();

        $this->assertDatabaseHas('order_master_responses', [
            'order_id' => $order->id,
            'master_id' => $master->id,
        ]);
    }

    public function test_guest_cannot_respond(): void
    {
        $this->postJson(route('api.v1.master.orders.respond', $this->order()))->assertUnauthorized();
    }
}
