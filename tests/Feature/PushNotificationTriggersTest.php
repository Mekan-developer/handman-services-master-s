<?php

namespace Tests\Feature;

use App\Actions\ApproveOrderResponseAction;
use App\Actions\CancelClientOrderAction;
use App\Actions\CompleteMasterOrderAction;
use App\Actions\StartMasterOrderAction;
use App\Actions\UpdateOrderStatusAction;
use App\Enums\OrderResponseStatus;
use App\Enums\OrderStatus;
use App\Events\MasterRespondedToOrder;
use App\Events\OrderSearchRadiusExpanded;
use App\Events\OrderSearchStarted;
use App\Models\Category;
use App\Models\ClientDevice;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use App\Notifications\Push\MasterAssignedNotification;
use App\Notifications\Push\MasterRespondedNotification;
use App\Notifications\Push\NewOrderNearbyNotification;
use App\Notifications\Push\OrderCancelledNotification;
use App\Notifications\Push\OrderStatusChangedNotification;
use App\Notifications\Push\ResponseApprovedNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Which order events push to whom. Firebase is out of the picture here —
 * Notification::fake() captures what would have been sent.
 */
class PushNotificationTriggersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ORDER_LAT = 37.95;

    private const ORDER_LNG = 58.38;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function respond(Order $order, Master $master): OrderMasterResponse
    {
        return OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => $master->id,
            'status' => OrderResponseStatus::Pending,
        ]);
    }

    /** A master who could take orders in `$category`, standing `$latOffset` degrees north of the order. */
    private function masterNearby(Category $category, float $latOffset, bool $withDevice = true): Master
    {
        $master = Master::factory()->create();
        $master->categories()->attach($category);

        MasterLocation::factory()->for($master)->create([
            'latitude' => self::ORDER_LAT + $latOffset,
            'longitude' => self::ORDER_LNG,
            'recorded_at' => now(),
        ]);

        if ($withDevice) {
            ClientDevice::factory()->for($master->client)->create();
        }

        return $master;
    }

    private function searchingOrder(Category $category, int $radiusKm = 5): Order
    {
        return Order::factory()
            ->forCategory($category)
            ->at(self::ORDER_LAT, self::ORDER_LNG)
            ->searching($radiusKm)
            ->create();
    }

    // ── client ─────────────────────────────────────────────────────────────

    public function test_client_is_told_when_a_master_responds(): void
    {
        $order = Order::factory()->searching()->create();
        $response = $this->respond($order, Master::factory()->create());

        MasterRespondedToOrder::dispatch($response->load('master'));

        Notification::assertSentTo(
            $order->client,
            MasterRespondedNotification::class,
            fn (MasterRespondedNotification $n) => $n->response->is($response),
        );
    }

    public function test_approving_a_response_tells_client_and_winning_master(): void
    {
        $order = Order::factory()->searching()->create();
        $master = Master::factory()->create();
        $response = $this->respond($order, $master);

        app(ApproveOrderResponseAction::class)->handle($order, $response);

        Notification::assertSentTo($order->client, MasterAssignedNotification::class);
        Notification::assertSentTo(
            $master,
            ResponseApprovedNotification::class,
            fn (ResponseApprovedNotification $n) => $n->order->is($order),
        );
    }

    public function test_client_is_told_when_master_starts_and_completes(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->create();

        app(StartMasterOrderAction::class)->handle($master, $order);
        app(CompleteMasterOrderAction::class)->handle($master, $order->fresh());

        Notification::assertSentTo(
            $order->client,
            OrderStatusChangedNotification::class,
            fn (OrderStatusChangedNotification $n) => $n->status === OrderStatus::InProgress,
        );
        Notification::assertSentTo(
            $order->client,
            OrderStatusChangedNotification::class,
            fn (OrderStatusChangedNotification $n) => $n->status === OrderStatus::Completed,
        );
    }

    public function test_cancellation_is_not_pushed_to_the_client_who_cancelled(): void
    {
        $order = Order::factory()->searching()->create();

        app(CancelClientOrderAction::class)->handle($order);

        Notification::assertNotSentTo($order->client, OrderStatusChangedNotification::class);
    }

    // ── master ─────────────────────────────────────────────────────────────

    public function test_masters_who_responded_are_told_when_client_cancels(): void
    {
        $order = Order::factory()->searching()->create();
        $responded = Master::factory()->create();
        $bystander = Master::factory()->create();
        $this->respond($order, $responded);

        app(CancelClientOrderAction::class)->handle($order);

        Notification::assertSentTo(
            $responded,
            OrderCancelledNotification::class,
            fn (OrderCancelledNotification $n) => $n->order->is($order),
        );
        Notification::assertNotSentTo($bystander, OrderCancelledNotification::class);
    }

    public function test_assigned_master_is_told_when_order_is_cancelled(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->create();

        app(UpdateOrderStatusAction::class)->handle($order, OrderStatus::Cancelled);

        Notification::assertSentTo($master, OrderCancelledNotification::class);
    }

    public function test_new_order_reaches_eligible_masters_inside_the_radius(): void
    {
        $category = Category::factory()->create();

        $near = $this->masterNearby($category, 0.01);
        $far = $this->masterNearby($category, 0.5);
        $otherTrade = $this->masterNearby(Category::factory()->create(), 0.01);
        $noPhone = $this->masterNearby($category, 0.01, withDevice: false);
        $unavailable = $this->masterNearby($category, 0.01);
        $unavailable->update(['is_available' => false]);
        $expired = $this->masterNearby($category, 0.01);
        $expired->update(['access_expires_at' => now()->subDay()]);

        $order = $this->searchingOrder($category);

        OrderSearchStarted::dispatch($order);

        Notification::assertSentTo(
            $near,
            NewOrderNearbyNotification::class,
            fn (NewOrderNearbyNotification $n) => $n->order->is($order),
        );

        foreach ([$far, $otherTrade, $noPhone, $unavailable, $expired] as $master) {
            Notification::assertNotSentTo($master, NewOrderNearbyNotification::class);
        }
    }

    public function test_masters_are_not_offered_their_own_order(): void
    {
        $category = Category::factory()->create();
        $master = $this->masterNearby($category, 0.01);

        $order = Order::factory()
            ->for($master->client)
            ->forCategory($category)
            ->at(self::ORDER_LAT, self::ORDER_LNG)
            ->searching()
            ->create();

        OrderSearchStarted::dispatch($order);

        Notification::assertNotSentTo($master, NewOrderNearbyNotification::class);
    }

    public function test_radius_expansion_only_reaches_the_new_ring(): void
    {
        $category = Category::factory()->create();

        $inner = $this->masterNearby($category, 0.01);   // ~1 km
        $ring = $this->masterNearby($category, 0.07);    // ~8 km

        $order = $this->searchingOrder($category, radiusKm: 10);

        OrderSearchRadiusExpanded::dispatch($order, 5);

        Notification::assertSentTo($ring, NewOrderNearbyNotification::class);
        Notification::assertNotSentTo($inner, NewOrderNearbyNotification::class);
    }

    public function test_masters_who_declined_or_responded_are_not_pushed_again(): void
    {
        $category = Category::factory()->create();
        $responded = $this->masterNearby($category, 0.01);

        $order = $this->searchingOrder($category);
        $this->respond($order, $responded);

        OrderSearchRadiusExpanded::dispatch($order, 0);

        Notification::assertNotSentTo($responded, NewOrderNearbyNotification::class);
    }

    public function test_taken_order_is_not_pushed_to_masters(): void
    {
        $category = Category::factory()->create();
        $master = $this->masterNearby($category, 0.01);

        $order = $this->searchingOrder($category);
        $order->update(['status' => OrderStatus::Cancelled]);

        OrderSearchStarted::dispatch($order);

        Notification::assertNotSentTo($master, NewOrderNearbyNotification::class);
    }
}
