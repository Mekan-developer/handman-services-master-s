<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDeclineOrderTest extends TestCase
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

    private function master(): Master
    {
        $master = Master::factory()->create(['is_available' => true]);
        $master->categories()->sync([$this->category->id]);

        MasterLocation::factory()->create([
            'master_id' => $master->id,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'recorded_at' => now(),
        ]);

        return $master;
    }

    private function order(): Order
    {
        return Order::factory()
            ->forCategory($this->category)
            ->at(self::LAT + 5 / Order::KM_PER_LAT_DEGREE, self::LNG)
            ->searching(20)
            ->create();
    }

    /**
     * Authenticate as a master for the next request.
     *
     * The sanctum guard caches the resolved user for the lifetime of the test,
     * so switching identities mid-test requires dropping that cache — otherwise
     * every later request silently runs as the first master.
     */
    private function actingAsMaster(Master $master): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($master->createToken('mobile')->plainTextToken);
    }

    private function feedFor(Master $master): array
    {
        return $this->actingAsMaster($master)
            ->getJson(route('api.v1.master.orders.available'))
            ->json('data');
    }

    public function test_declined_order_disappears_from_that_masters_feed(): void
    {
        $master = $this->master();
        $order = $this->order();

        $this->assertCount(1, $this->feedFor($master));

        $this->actingAsMaster($master)
            ->postJson(route('api.v1.master.orders.decline', $order))
            ->assertNoContent();

        $this->assertDatabaseHas('order_master_declines', [
            'order_id' => $order->id,
            'master_id' => $master->id,
        ]);
        $this->assertCount(0, $this->feedFor($master));
    }

    public function test_declining_does_not_hide_the_order_from_other_masters(): void
    {
        $declining = $this->master();
        $other = $this->master();
        $order = $this->order();

        $this->actingAsMaster($declining)
            ->postJson(route('api.v1.master.orders.decline', $order))
            ->assertNoContent();

        $this->assertCount(1, $this->feedFor($other));
        $this->assertNull($order->fresh()->master_id);
        $this->assertNull($order->fresh()->search_expired_at);
    }

    public function test_declining_twice_is_idempotent(): void
    {
        $master = $this->master();
        $order = $this->order();
        $route = route('api.v1.master.orders.decline', $order);

        $this->actingAsMaster($master)->postJson($route)->assertNoContent();
        $this->actingAsMaster($master)->postJson($route)->assertNoContent();

        $this->assertDatabaseCount('order_master_declines', 1);
    }

    public function test_an_already_claimed_order_cannot_be_declined(): void
    {
        $master = $this->master();
        $order = $this->order();
        $order->update(['master_id' => Master::factory()->create()->id]);

        $this->actingAsMaster($master)
            ->postJson(route('api.v1.master.orders.decline', $order->fresh()))
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.already_claimed'));
    }

    /** Declining only hides the offer; a master who changes their mind may still claim it. */
    public function test_a_declined_order_can_still_be_claimed_directly(): void
    {
        $master = $this->master();
        $order = $this->order();

        $this->actingAsMaster($master)
            ->postJson(route('api.v1.master.orders.decline', $order))
            ->assertNoContent();

        $this->actingAsMaster($master)
            ->postJson(route('api.v1.master.orders.respond', $order->fresh()))
            ->assertOk();

        $this->assertSame($master->id, $order->fresh()->master_id);
    }

    public function test_guest_cannot_decline(): void
    {
        $this->postJson(route('api.v1.master.orders.decline', $this->order()))->assertUnauthorized();
    }
}
