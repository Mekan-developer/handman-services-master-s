<?php

namespace Tests\Feature;

use App\Models\Master;
use App\Models\MasterSubscription;
use App\Notifications\Push\SubscriptionExpiredNotification;
use App\Notifications\Push\SubscriptionExpiringNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionPushNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    // ── expiring ───────────────────────────────────────────────────────────

    public function test_master_is_reminded_a_day_before_expiry_only_once(): void
    {
        $master = Master::factory()->create();
        $subscription = MasterSubscription::factory()->forMaster($master)->create([
            'expires_at' => now()->addHours(20),
        ]);

        $this->artisan('subscriptions:remind-expiring')
            ->expectsOutput('Reminded 1 master(s).')
            ->assertSuccessful();
        $this->artisan('subscriptions:remind-expiring')
            ->expectsOutput('Reminded 0 master(s).')
            ->assertSuccessful();

        Notification::assertSentToTimes($master, SubscriptionExpiringNotification::class, 1);
        $this->assertNotNull($subscription->fresh()->expiry_reminded_at);
    }

    public function test_no_reminder_while_expiry_is_further_than_a_day(): void
    {
        $master = Master::factory()->create();
        MasterSubscription::factory()->forMaster($master)->create(['expires_at' => now()->addDays(3)]);

        $this->artisan('subscriptions:remind-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_no_reminder_when_renewal_is_already_queued(): void
    {
        $master = Master::factory()->create();
        $running = MasterSubscription::factory()->forMaster($master)->create(['expires_at' => now()->addHours(20)]);
        MasterSubscription::factory()->forMaster($master)->pending()->create([
            'starts_at' => $running->expires_at,
            'expires_at' => $running->expires_at->copy()->addDays(30),
        ]);

        $this->artisan('subscriptions:remind-expiring')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // ── expired ────────────────────────────────────────────────────────────

    public function test_master_is_told_when_access_runs_out(): void
    {
        $master = Master::factory()->create(['access_expires_at' => now()->subDay()]);
        $subscription = MasterSubscription::factory()->forMaster($master)->overdue()->create();

        $this->artisan('subscriptions:expire')->assertSuccessful();

        Notification::assertSentTo(
            $master,
            SubscriptionExpiredNotification::class,
            fn (SubscriptionExpiredNotification $n) => $n->subscription->is($subscription),
        );
    }

    public function test_no_expired_push_when_queued_subscription_takes_over(): void
    {
        $master = Master::factory()->create();
        $running = MasterSubscription::factory()->forMaster($master)->overdue()->create();
        MasterSubscription::factory()->forMaster($master)->pending()->create([
            'starts_at' => $running->expires_at,
            'expires_at' => $running->expires_at->copy()->addDays(30),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        Notification::assertNotSentTo($master, SubscriptionExpiredNotification::class);
    }
}
