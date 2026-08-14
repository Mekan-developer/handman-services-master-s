<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Master;
use App\Models\MasterSubscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExpireMasterSubscriptionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_expires_overdue_subscriptions_and_closes_access(): void
    {
        $master = Master::factory()->create(['access_expires_at' => now()->subDay()]);
        $subscription = MasterSubscription::factory()->forMaster($master)->overdue()->create();

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $subscription->fresh()->status);
        $this->assertFalse($master->fresh()->hasActiveAccess());
    }

    public function test_command_leaves_running_subscriptions_alone(): void
    {
        $master = Master::factory()->create();
        $subscription = MasterSubscription::factory()->forMaster($master)->create([
            'expires_at' => now()->addDays(10),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        $this->assertTrue($master->fresh()->hasActiveAccess());
    }

    public function test_command_promotes_the_queued_subscription_and_extends_access(): void
    {
        $master = Master::factory()->create();

        $running = MasterSubscription::factory()->forMaster($master)->overdue()->create();
        $queued = MasterSubscription::factory()->forMaster($master)->pending()->create([
            'starts_at' => $running->expires_at,
            'expires_at' => $running->expires_at->copy()->addDays(30),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $running->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $queued->fresh()->status);

        $master->refresh();

        $this->assertTrue($master->hasActiveAccess());
        $this->assertSame(
            $queued->fresh()->expires_at->toDateTimeString(),
            $master->access_expires_at->toDateTimeString(),
        );
    }

    public function test_command_does_not_promote_a_queued_subscription_while_another_runs(): void
    {
        $master = Master::factory()->create();

        MasterSubscription::factory()->forMaster($master)->create(['expires_at' => now()->addDays(10)]);
        $queued = MasterSubscription::factory()->forMaster($master)->pending()->create([
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(40),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Pending, $queued->fresh()->status);
    }

    public function test_command_is_idempotent(): void
    {
        $master = Master::factory()->create();
        MasterSubscription::factory()->forMaster($master)->overdue()->create();

        $this->artisan('subscriptions:expire')->assertSuccessful();
        $accessAfterFirstRun = $master->fresh()->access_expires_at;

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(
            $accessAfterFirstRun->toDateTimeString(),
            $master->fresh()->access_expires_at->toDateTimeString(),
        );
        $this->assertSame(1, MasterSubscription::where('status', SubscriptionStatus::Expired->value)->count());
    }

    public function test_expiring_one_master_does_not_touch_another(): void
    {
        $lapsed = Master::factory()->create();
        $current = Master::factory()->create();

        MasterSubscription::factory()->forMaster($lapsed)->overdue()->create();
        MasterSubscription::factory()->forMaster($current)->create(['expires_at' => now()->addDays(20)]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertFalse($lapsed->fresh()->hasActiveAccess());
        $this->assertTrue($current->fresh()->hasActiveAccess());
    }
}
