<?php

namespace Tests\Feature;

use App\Actions\RestartOrderSearchAction;
use App\Events\OrderSearchStarted;
use App\Exceptions\OrderException;
use App\Models\Master;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RestartOrderSearchActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function action(): RestartOrderSearchAction
    {
        return app(RestartOrderSearchAction::class);
    }

    public function test_restarts_search_on_an_order_that_never_entered_the_pool(): void
    {
        Setting::create(['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => '20']);

        $order = Order::factory()->create([
            'search_started_at' => null,
            'search_radius_km' => null,
            'search_expired_at' => null,
        ]);

        $this->action()->handle($order);

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->search_started_at);
        $this->assertSame(20, $fresh->search_radius_km);
        $this->assertNull($fresh->search_expired_at);
    }

    public function test_restarts_a_search_that_had_expired(): void
    {
        Setting::create(['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => '20']);

        $order = Order::factory()->searching(20, now()->subMinutes(10))->searchExpired()->create();

        $this->action()->handle($order);

        $fresh = $order->fresh();
        $this->assertNull($fresh->search_expired_at);
        $this->assertFalse($fresh->needsManualAssignment());
    }

    public function test_dispatches_order_search_started(): void
    {
        Event::fake([OrderSearchStarted::class]);
        $order = Order::factory()->create();

        $this->action()->handle($order);

        Event::assertDispatched(
            OrderSearchStarted::class,
            fn (OrderSearchStarted $event) => $event->order->id === $order->id
        );
    }

    public function test_cannot_restart_search_on_an_assigned_order(): void
    {
        $order = Order::factory()->forMaster(Master::factory()->create())->assigned()->create();

        $this->expectException(OrderException::class);

        $this->action()->handle($order);
    }

    public function test_cannot_restart_search_on_a_completed_order(): void
    {
        $order = Order::factory()->completed()->create();

        $this->expectException(OrderException::class);

        $this->action()->handle($order);
    }
}
