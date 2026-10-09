<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MasterSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Issuing ───────────────────────────────────────────────────────────────

    public function test_issuing_a_subscription_opens_master_access(): void
    {
        $admin = $this->actingAsAdmin();
        $master = Master::factory()->create(['access_expires_at' => now()->subDay()]);
        $plan = SubscriptionPlan::factory()->days(30)->create(['price' => 150]);

        $this->post(route('masters.subscriptions.store', $master), [
            'subscription_plan_id' => $plan->id,
            'note' => 'Оплата наличными',
        ])->assertRedirect(route('subscriptions.index'));

        $subscription = MasterSubscription::firstOrFail();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($plan->name_ru, $subscription->plan_name);
        $this->assertEqualsWithDelta(150.0, (float) $subscription->price_paid, 0.01);
        $this->assertSame(30, $subscription->duration_days);
        $this->assertSame($admin->id, $subscription->created_by);

        $master->refresh();

        $this->assertTrue($master->hasActiveAccess());
        $this->assertSame(
            $subscription->expires_at->toDateTimeString(),
            $master->access_expires_at->toDateTimeString(),
        );
    }

    public function test_expiry_is_computed_from_the_snapshot_not_the_current_plan(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create(['access_expires_at' => now()->subDay()]);
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);

        $subscription = MasterSubscription::firstOrFail();

        $plan->update(['duration_days' => 365]);

        $this->assertSame(30, $subscription->fresh()->duration_days);
        $this->assertSame(
            $subscription->starts_at->copy()->addDays(30)->toDateTimeString(),
            $subscription->expires_at->toDateTimeString(),
        );
    }

    public function test_price_can_be_overridden_manually(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $plan = SubscriptionPlan::factory()->create(['price' => 150]);

        $this->post(route('masters.subscriptions.store', $master), [
            'subscription_plan_id' => $plan->id,
            'price_paid' => 0,
            'note' => 'Промо',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(0.0, (float) MasterSubscription::firstOrFail()->price_paid, 0.01);
    }

    public function test_inactive_plan_cannot_be_used_for_a_new_subscription(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create(['access_expires_at' => now()->subDay()]);
        $plan = SubscriptionPlan::factory()->inactive()->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id])
            ->assertRedirect(route('subscriptions.index'))
            ->assertSessionHas('notification', fn ($notification) => $notification['type'] === 'error');

        $this->assertDatabaseCount('master_subscriptions', 0);
        $this->assertFalse($master->fresh()->hasActiveAccess());
    }

    public function test_subscription_bought_from_a_now_inactive_plan_keeps_working(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create(['access_expires_at' => now()->subDay()]);
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);

        $plan->update(['is_active' => false]);

        $this->assertSame(SubscriptionStatus::Active, MasterSubscription::firstOrFail()->status);
        $this->assertTrue($master->fresh()->hasActiveAccess());
    }

    // ── Renewal ───────────────────────────────────────────────────────────────

    public function test_renewal_extends_the_running_subscription_at_once(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $monthly = SubscriptionPlan::factory()->days(30)->create(['price' => 150]);
        $short = SubscriptionPlan::factory()->days(3)->create(['name_ru' => 'Три дня', 'price' => 20]);

        $this->post(route('masters.subscriptions.store', $master), [
            'subscription_plan_id' => $monthly->id,
            'note' => 'Наличные',
        ]);
        $originalEnd = MasterSubscription::firstOrFail()->expires_at;

        $this->post(route('masters.subscriptions.store', $master), [
            'subscription_plan_id' => $short->id,
            'note' => 'Перевод',
        ]);

        // Still one subscription — nothing queued — that simply lasts longer.
        $this->assertDatabaseCount('master_subscriptions', 1);
        $subscription = MasterSubscription::firstOrFail();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($originalEnd->copy()->addDays(3)->toDateTimeString(), $subscription->expires_at->toDateTimeString());
        $this->assertSame(33, $subscription->duration_days);
        $this->assertEqualsWithDelta(170.0, (float) $subscription->price_paid, 0.01);
        $this->assertSame($short->id, $subscription->subscription_plan_id);
        $this->assertSame('Три дня', $subscription->plan_name);
        $this->assertSame('Наличные; Перевод', $subscription->note);

        $this->assertSame(
            $subscription->expires_at->toDateTimeString(),
            $master->fresh()->access_expires_at->toDateTimeString(),
        );
    }

    public function test_only_the_masters_newest_subscription_is_flagged_for_renewal(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $old = MasterSubscription::factory()->forMaster($master)->expired()->create();
        $newest = MasterSubscription::factory()->forMaster($master)->create();
        $other = MasterSubscription::factory()->create();

        $this->get(route('subscriptions.index'))
            ->assertInertia(fn ($page) => $page
                ->where('subscriptions.data', fn ($rows) => collect($rows)->pluck('is_latest', 'id')->all() === [
                    $other->id => true,
                    $newest->id => true,
                    $old->id => false,
                ]));
    }

    public function test_renewal_rearms_the_expiry_reminder(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $running = MasterSubscription::factory()->forMaster($master)->create([
            'expires_at' => now()->addHours(20),
            'expiry_reminded_at' => now(),
        ]);

        $this->post(route('masters.subscriptions.store', $master), [
            'subscription_plan_id' => SubscriptionPlan::factory()->days(30)->create()->id,
        ]);

        $this->assertNull($running->fresh()->expiry_reminded_at);
    }

    public function test_renewal_of_a_legacy_queue_extends_its_tail(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $running = MasterSubscription::factory()->forMaster($master)->create(['expires_at' => now()->addDays(10)]);
        $queued = MasterSubscription::factory()->forMaster($master)->pending()->create([
            'starts_at' => $running->expires_at,
            'expires_at' => $running->expires_at->copy()->addDays(30),
        ]);

        $this->post(route('masters.subscriptions.store', $master), [
            'subscription_plan_id' => SubscriptionPlan::factory()->days(5)->create()->id,
        ]);

        $this->assertDatabaseCount('master_subscriptions', 2);
        $this->assertSame($running->expires_at->toDateTimeString(), $running->fresh()->expires_at->toDateTimeString());
        $this->assertSame(
            $queued->expires_at->copy()->addDays(5)->toDateTimeString(),
            $queued->fresh()->expires_at->toDateTimeString(),
        );
    }

    public function test_buying_after_an_overdue_subscription_starts_a_fresh_one(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->expired()->create();
        $overdue = MasterSubscription::factory()->forMaster($master)->overdue()->create();

        $this->post(route('masters.subscriptions.store', $master), [
            'subscription_plan_id' => SubscriptionPlan::factory()->days(30)->create()->id,
        ]);

        $fresh = MasterSubscription::whereKeyNot($overdue->id)->firstOrFail();

        $this->assertSame(SubscriptionStatus::Expired, $overdue->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $fresh->status);
        $this->assertTrue($fresh->starts_at->isToday());
        $this->assertTrue($master->fresh()->hasActiveAccess());
    }

    public function test_master_never_has_two_active_subscriptions(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);
        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);
        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);

        $this->assertSame(1, MasterSubscription::where('master_id', $master->id)
            ->where('status', SubscriptionStatus::Active->value)
            ->count());
    }

    // ── Status changes ────────────────────────────────────────────────────────

    public function test_cancelling_the_only_subscription_closes_access(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);
        $subscription = MasterSubscription::firstOrFail();

        $this->post(route('subscriptions.update-status', $subscription), ['status' => 'cancelled'])
            ->assertRedirect(route('subscriptions.index'));

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);
        $this->assertFalse($master->fresh()->hasActiveAccess());
    }

    public function test_cancelling_a_queued_subscription_leaves_the_running_one_alone(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        // Queues are no longer created, but older data may still carry one.
        $running = MasterSubscription::factory()->forMaster($master)->fromPlan($plan)->create();
        $queued = MasterSubscription::factory()->forMaster($master)->fromPlan($plan)->pending()->create([
            'starts_at' => $running->expires_at,
            'expires_at' => $running->expires_at->copy()->addDays(30),
        ]);

        $this->post(route('subscriptions.update-status', $queued), ['status' => 'cancelled'])->assertRedirect();

        $this->assertSame(SubscriptionStatus::Active, $running->fresh()->status);
        $this->assertTrue($master->fresh()->hasActiveAccess());
        $this->assertSame(
            $running->expires_at->toDateTimeString(),
            $master->fresh()->access_expires_at->toDateTimeString(),
        );
    }

    public function test_a_final_subscription_cannot_change_status_again(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $subscription = MasterSubscription::factory()->forMaster($master)->cancelled()->create();

        $this->post(route('subscriptions.update-status', $subscription), ['status' => 'active'])
            ->assertRedirect(route('subscriptions.index'))
            ->assertSessionHas('notification', fn ($notification) => $notification['type'] === 'error');

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);
    }

    public function test_activating_while_another_is_running_is_rejected(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        MasterSubscription::factory()->forMaster($master)->create();
        $queued = MasterSubscription::factory()->forMaster($master)->pending()->create();

        $this->post(route('subscriptions.update-status', $queued), ['status' => 'active'])
            ->assertSessionHas('notification', fn ($notification) => $notification['type'] === 'error');

        $this->assertSame(SubscriptionStatus::Pending, $queued->fresh()->status);
    }

    public function test_status_must_be_a_known_value(): void
    {
        $this->actingAsAdmin();
        $subscription = MasterSubscription::factory()->create();

        $this->post(route('subscriptions.update-status', $subscription), ['status' => 'not_a_status'])
            ->assertSessionHasErrors('status');
    }

    // ── Update & delete ───────────────────────────────────────────────────────

    public function test_admin_can_correct_price_and_note(): void
    {
        $this->actingAsAdmin();
        $subscription = MasterSubscription::factory()->create(['price_paid' => 150]);

        $this->put(route('subscriptions.update', $subscription), ['price_paid' => 120, 'note' => 'Скидка'])
            ->assertRedirect(route('subscriptions.index'));

        $subscription->refresh();

        $this->assertEqualsWithDelta(120.0, (float) $subscription->price_paid, 0.01);
        $this->assertSame('Скидка', $subscription->note);
    }

    public function test_deleting_a_subscription_recomputes_access(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);
        $subscription = MasterSubscription::firstOrFail();

        $this->delete(route('subscriptions.destroy', $subscription))->assertRedirect(route('subscriptions.index'));

        $this->assertDatabaseCount('master_subscriptions', 0);
        $this->assertFalse($master->fresh()->hasActiveAccess());
    }

    public function test_master_categories_survive_an_access_resync(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->hasAttached(Category::factory()->count(2))->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);

        $this->assertSame(2, $master->fresh()->categories()->count());
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_manager_cannot_issue_a_subscription(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $master = Master::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id])
            ->assertForbidden();

        $this->assertDatabaseCount('master_subscriptions', 0);
    }

    public function test_manager_cannot_change_subscription_status(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $subscription = MasterSubscription::factory()->create();

        $this->post(route('subscriptions.update-status', $subscription), ['status' => 'cancelled'])
            ->assertForbidden();

        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }
}
