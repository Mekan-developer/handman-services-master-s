<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\City;
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

    public function test_renewal_starts_from_the_current_expiry_not_from_now(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);
        $first = MasterSubscription::firstOrFail();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);
        $second = MasterSubscription::latest('id')->firstOrFail();

        // Queued behind the running one, so the invariant "at most one Active" holds.
        $this->assertSame(SubscriptionStatus::Pending, $second->status);
        $this->assertSame($first->expires_at->toDateTimeString(), $second->starts_at->toDateTimeString());
        $this->assertSame(
            $first->expires_at->copy()->addDays(30)->toDateTimeString(),
            $second->expires_at->toDateTimeString(),
        );

        // Access reaches to the end of the whole chain.
        $this->assertSame(
            $second->expires_at->toDateTimeString(),
            $master->fresh()->access_expires_at->toDateTimeString(),
        );
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

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);
        $running = MasterSubscription::firstOrFail();

        $this->post(route('masters.subscriptions.store', $master), ['subscription_plan_id' => $plan->id]);
        $queued = MasterSubscription::latest('id')->firstOrFail();

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

    // ── Master creation with a plan ───────────────────────────────────────────

    public function test_creating_a_master_with_a_plan_issues_the_first_subscription(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $plan = SubscriptionPlan::factory()->days(90)->create(['price' => 400]);

        $this->post(route('masters.store'), [
            'city_id' => $city->id,
            'name' => 'Иван Иванов',
            'phone' => '+99362123456',
            'is_active' => true,
            'category_ids' => [],
            'subscription_plan_id' => $plan->id,
            'subscription_price' => 350,
        ])->assertRedirect(route('masters.index'));

        $master = Master::where('phone', '+99362123456')->firstOrFail();
        $subscription = MasterSubscription::where('master_id', $master->id)->firstOrFail();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertEqualsWithDelta(350.0, (float) $subscription->price_paid, 0.01);
        $this->assertTrue($master->hasActiveAccess());
    }

    public function test_creating_a_master_without_a_plan_leaves_access_closed(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $this->post(route('masters.store'), [
            'city_id' => $city->id,
            'name' => 'Без подписки',
            'phone' => '+99362123457',
            'is_active' => true,
            'category_ids' => [],
        ])->assertRedirect(route('masters.index'));

        $master = Master::where('phone', '+99362123457')->firstOrFail();

        $this->assertDatabaseCount('master_subscriptions', 0);
        $this->assertFalse($master->hasActiveAccess());
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
