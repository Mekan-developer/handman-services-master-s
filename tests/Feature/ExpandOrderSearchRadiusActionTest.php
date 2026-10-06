<?php

namespace Tests\Feature;

use App\Actions\ExpandOrderSearchRadiusAction;
use App\Events\OrderSearchExhausted;
use App\Events\OrderSearchRadiusExpanded;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderSearchExhaustedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpandOrderSearchRadiusActionTest extends TestCase
{
    use RefreshDatabase;

    private const INITIAL = 20;

    private const MAX = 80;

    private function action(): ExpandOrderSearchRadiusAction
    {
        return app(ExpandOrderSearchRadiusAction::class);
    }

    private function orderSearchingFor(int $seconds): Order
    {
        return Order::factory()
            ->searching(self::INITIAL, now()->subSeconds($seconds))
            ->create();
    }

    /**
     * radius(n) = n * initial, where n is the minute of the search the order is in.
     *
     * @return array<string, array{int, int}>
     */
    public static function elapsedSecondsProvider(): array
    {
        return [
            'minute 1 — just created' => [0, 20],
            'minute 1 — 59 seconds in' => [59, 20],
            'minute 2 — exactly one minute in' => [60, 40],
            'minute 2 — 119 seconds in' => [119, 40],
            'minute 3' => [120, 60],
            'minute 4 — last step inside the maximum' => [180, 80],
        ];
    }

    #[DataProvider('elapsedSecondsProvider')]
    public function test_radius_grows_by_one_step_per_elapsed_minute(int $elapsedSeconds, int $expectedRadius): void
    {
        $order = $this->orderSearchingFor($elapsedSeconds);

        $this->action()->handle($order, self::INITIAL, self::MAX);

        $this->assertSame($expectedRadius, $order->fresh()->search_radius_km);
        $this->assertNull($order->fresh()->search_expired_at);
    }

    public function test_search_expires_once_the_next_step_overshoots_the_maximum(): void
    {
        Event::fake([OrderSearchExhausted::class, OrderSearchRadiusExpanded::class]);

        $order = $this->orderSearchingFor(240);

        $this->action()->handle($order, self::INITIAL, self::MAX);

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->search_expired_at);
        $this->assertSame(self::MAX, $fresh->search_radius_km, 'radius is pinned at the maximum once the search ends');
        $this->assertTrue($fresh->needsManualAssignment());

        Event::assertDispatched(OrderSearchExhausted::class);
        Event::assertNotDispatched(OrderSearchRadiusExpanded::class);
    }

    public function test_an_expired_search_is_never_touched_again(): void
    {
        Event::fake([OrderSearchExhausted::class]);

        $order = Order::factory()->searching(self::MAX, now()->subMinutes(30))->searchExpired()->create();
        $expiredAt = $order->search_expired_at;

        $this->action()->handle($order, self::INITIAL, self::MAX);

        $this->assertEquals($expiredAt, $order->fresh()->search_expired_at);
        Event::assertNotDispatched(OrderSearchExhausted::class);
    }

    public function test_no_event_is_dispatched_when_the_radius_does_not_change(): void
    {
        Event::fake([OrderSearchRadiusExpanded::class]);

        $this->action()->handle($this->orderSearchingFor(10), self::INITIAL, self::MAX);

        Event::assertNotDispatched(OrderSearchRadiusExpanded::class);
    }

    public function test_expansion_broadcasts_so_master_apps_can_refresh(): void
    {
        Event::fake([OrderSearchRadiusExpanded::class]);

        $order = $this->orderSearchingFor(60);

        $this->action()->handle($order, self::INITIAL, self::MAX);

        Event::assertDispatched(
            OrderSearchRadiusExpanded::class,
            fn (OrderSearchRadiusExpanded $event) => $event->order->id === $order->id
                && $event->order->search_radius_km === 40
        );
    }

    public function test_administrators_are_notified_once_when_the_search_is_exhausted(): void
    {
        Notification::fake();

        $administrator = User::factory()->administrator()->create();
        $order = $this->orderSearchingFor(240);

        $this->action()->handle($order, self::INITIAL, self::MAX);

        Notification::assertSentToTimes($administrator, OrderSearchExhaustedNotification::class, 1);
    }

    public function test_the_scheduler_command_expands_every_searching_order(): void
    {
        Setting::create(['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => (string) self::INITIAL]);
        Setting::create(['key' => Setting::MASTER_SEARCH_MAX_RADIUS_KM, 'value' => (string) self::MAX]);

        $growing = $this->orderSearchingFor(120);
        $exhausted = $this->orderSearchingFor(600);
        $assigned = Order::factory()->searching(self::INITIAL, now()->subMinutes(5))->assigned()->create();

        $this->artisan('orders:expand-search-radius')->assertSuccessful();

        $this->assertSame(60, $growing->fresh()->search_radius_km);
        $this->assertNotNull($exhausted->fresh()->search_expired_at);
        $this->assertSame(self::INITIAL, $assigned->fresh()->search_radius_km, 'claimed orders are out of the sweep');
    }
}
