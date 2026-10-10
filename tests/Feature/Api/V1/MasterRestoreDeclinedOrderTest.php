<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Client;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use App\Models\OrderMasterDecline;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * "Return a declined order": the list of declines a master can still take back,
 * and taking one back within the restore window.
 */
class MasterRestoreDeclinedOrderTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 37.9601;

    private const LNG = 58.3261;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 12:00:00');

        $this->category = Category::factory()->child(Category::factory()->create())->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function decline(Order $order, Master $master, ?Carbon $at = null): OrderMasterDecline
    {
        $decline = OrderMasterDecline::create(['order_id' => $order->id, 'master_id' => $master->id]);
        $decline->forceFill(['created_at' => $at ?? now()])->save();

        return $decline;
    }

    /** See MasterDeclineOrderTest::actingAsMaster() — the sanctum guard caches the user. */
    private function actingAsMaster(Master $master): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($master->client->createToken('mobile-client')->plainTextToken);
    }

    // ── Declined list ─────────────────────────────────────────────────────────

    public function test_the_list_shows_only_own_restorable_declines_freshest_first(): void
    {
        $master = $this->master();
        $other = $this->master();

        $older = $this->order();
        $newer = $this->order();
        $this->decline($older, $master, now()->subMinutes(30));
        $this->decline($newer, $master, now()->subMinutes(5));

        $this->decline($this->order(), $other);
        $assigned = $this->order();
        $assigned->update(['status' => OrderStatus::Assigned, 'master_id' => $other->id]);
        $this->decline($assigned, $master);
        $cancelled = $this->order();
        $cancelled->update(['status' => OrderStatus::Cancelled]);
        $this->decline($cancelled, $master);
        $this->decline($this->order(), $master, now()->subMinutes(61));

        $this->actingAsMaster($master)
            ->getJson(route('api.v1.master.orders.declined'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.0.declined_at', now()->subMinutes(5)->toIso8601String())
            ->assertJsonPath('data.0.restore_until', now()->addMinutes(55)->toIso8601String())
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_list_follows_the_configured_window(): void
    {
        Setting::create(['key' => Setting::ORDER_DECLINE_RESTORE_MINUTES, 'value' => '10']);

        $master = $this->master();
        $this->decline($this->order(), $master, now()->subMinutes(11));
        $fresh = $this->order();
        $this->decline($fresh, $master, now()->subMinutes(10));

        $this->actingAsMaster($master)
            ->getJson(route('api.v1.master.orders.declined'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fresh->id)
            ->assertJsonPath('data.0.restore_until', now()->toIso8601String());
    }

    // ── Restore ───────────────────────────────────────────────────────────────

    public function test_restoring_returns_the_order_to_the_feed(): void
    {
        $master = $this->master();
        $order = $this->order();
        $this->decline($order, $master, now()->subMinutes(10));

        $this->actingAsMaster($master)
            ->getJson(route('api.v1.master.orders.available'))
            ->assertJsonCount(0, 'data');

        $this->actingAsMaster($master)
            ->deleteJson(route('api.v1.master.orders.restore-decline', $order))
            ->assertNoContent();

        $this->assertDatabaseMissing('order_master_declines', ['order_id' => $order->id, 'master_id' => $master->id]);

        $this->actingAsMaster($master)
            ->getJson(route('api.v1.master.orders.available'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id);

        $this->actingAsMaster($master)
            ->getJson(route('api.v1.master.orders.by-category'))
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_restoring_an_order_that_was_never_declined_fails(): void
    {
        $master = $this->master();
        $order = $this->order();

        $this->actingAsMaster($master)
            ->deleteJson(route('api.v1.master.orders.restore-decline', $order))
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.not_declined'));
    }

    public function test_a_master_cannot_restore_another_masters_decline(): void
    {
        $owner = $this->master();
        $intruder = $this->master();
        $order = $this->order();
        $this->decline($order, $owner);

        $this->actingAsMaster($intruder)
            ->deleteJson(route('api.v1.master.orders.restore-decline', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('order_master_declines', ['order_id' => $order->id, 'master_id' => $owner->id]);
    }

    public function test_restoring_an_order_another_master_already_took_fails(): void
    {
        $master = $this->master();
        $order = $this->order();
        $this->decline($order, $master);
        $order->update(['status' => OrderStatus::Assigned, 'master_id' => $this->master()->id]);

        $this->actingAsMaster($master)
            ->deleteJson(route('api.v1.master.orders.restore-decline', $order))
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.already_claimed'));

        $this->assertDatabaseHas('order_master_declines', ['order_id' => $order->id, 'master_id' => $master->id]);
    }

    public function test_restoring_a_cancelled_order_fails(): void
    {
        $master = $this->master();
        $order = $this->order();
        $this->decline($order, $master);
        $order->update(['status' => OrderStatus::Cancelled]);

        $this->actingAsMaster($master)
            ->deleteJson(route('api.v1.master.orders.restore-decline', $order))
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.no_longer_available'));

        $this->assertDatabaseHas('order_master_declines', ['order_id' => $order->id, 'master_id' => $master->id]);
    }

    public function test_restoring_exactly_at_the_end_of_the_window_succeeds(): void
    {
        $master = $this->master();
        $order = $this->order();
        $this->decline($order, $master);

        Carbon::setTestNow(now()->addMinutes(60));

        $this->actingAsMaster($master)
            ->deleteJson(route('api.v1.master.orders.restore-decline', $order))
            ->assertNoContent();
    }

    public function test_restoring_after_the_window_fails(): void
    {
        $master = $this->master();
        $order = $this->order();
        $this->decline($order, $master);

        Carbon::setTestNow(now()->addMinutes(60)->addSecond());

        $this->actingAsMaster($master)
            ->deleteJson(route('api.v1.master.orders.restore-decline', $order))
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.restore_window_expired'));

        $this->assertDatabaseHas('order_master_declines', ['order_id' => $order->id, 'master_id' => $master->id]);
    }

    public function test_restoring_a_missing_order_returns_404(): void
    {
        $this->actingAsMaster($this->master())
            ->deleteJson(route('api.v1.master.orders.restore-decline', 999999))
            ->assertNotFound();
    }

    // ── Access ────────────────────────────────────────────────────────────────

    public function test_guests_cannot_reach_the_endpoints(): void
    {
        $order = $this->order();

        $this->getJson(route('api.v1.master.orders.declined'))->assertUnauthorized();
        $this->deleteJson(route('api.v1.master.orders.restore-decline', $order))->assertUnauthorized();
    }

    public function test_a_client_without_a_master_profile_cannot_reach_the_endpoints(): void
    {
        $order = $this->order();
        $token = Client::factory()->create()->createToken('mobile-client')->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.master.orders.declined'))
            ->assertForbidden()
            ->assertJsonPath('reason', 'not_a_master');

        $this->withToken($token)
            ->deleteJson(route('api.v1.master.orders.restore-decline', $order))
            ->assertForbidden()
            ->assertJsonPath('reason', 'not_a_master');
    }
}
