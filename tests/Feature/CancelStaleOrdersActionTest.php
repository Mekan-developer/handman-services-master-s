<?php

namespace Tests\Feature;

use App\Actions\CancelStaleOrdersAction;
use App\Enums\OrderResponseStatus;
use App\Enums\OrderStatus;
use App\Events\OrderResponseWithdrawn;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CancelStaleOrdersActionTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULT_HOURS = 48;

    private function action(): CancelStaleOrdersAction
    {
        return app(CancelStaleOrdersAction::class);
    }

    private function orderCreatedHoursAgo(int $hours): Order
    {
        $order = Order::factory()->create();

        Order::where('id', $order->id)->update(['created_at' => now()->subHours($hours)]);

        return $order->fresh();
    }

    public function test_stale_order_is_cancelled_with_the_system_reason(): void
    {
        $order = $this->orderCreatedHoursAgo(self::DEFAULT_HOURS + 1);

        $cancelled = $this->action()->handle($order);

        $this->assertSame(OrderStatus::Cancelled, $cancelled->status);
        $this->assertSame(__('orders.notifications.auto_cancelled_reason'), $cancelled->cancel_reason);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_pending_responses_are_withdrawn_when_the_order_auto_cancels(): void
    {
        Event::fake([OrderResponseWithdrawn::class]);

        $order = $this->orderCreatedHoursAgo(self::DEFAULT_HOURS + 1);
        $response = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Pending,
        ]);

        $this->action()->handle($order);

        $fresh = $response->fresh();
        $this->assertSame(OrderResponseStatus::Rejected, $fresh->status);
        $this->assertNotNull($fresh->decided_at);

        Event::assertDispatched(
            OrderResponseWithdrawn::class,
            fn (OrderResponseWithdrawn $event) => $event->response->id === $response->id
        );
    }

    public function test_the_scheduler_command_cancels_every_stale_order(): void
    {
        $stale = $this->orderCreatedHoursAgo(self::DEFAULT_HOURS + 5);
        $fresh = $this->orderCreatedHoursAgo(self::DEFAULT_HOURS - 5);
        $assigned = $this->orderCreatedHoursAgo(self::DEFAULT_HOURS + 5);
        Order::where('id', $assigned->id)->update([
            'status' => OrderStatus::Assigned,
            'master_id' => Master::factory()->create()->id,
        ]);

        $this->artisan('orders:cancel-stale-orders')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $stale->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $fresh->fresh()->status);
        $this->assertSame(OrderStatus::Assigned, $assigned->fresh()->status, 'assigned orders are out of the sweep');
    }

    public function test_the_command_respects_the_configured_deadline(): void
    {
        Setting::create(['key' => Setting::ORDER_AUTO_CANCEL_HOURS, 'value' => '5']);

        $order = $this->orderCreatedHoursAgo(6);

        $this->artisan('orders:cancel-stale-orders')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }
}
