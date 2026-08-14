<?php

namespace Tests\Feature;

use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name_ru' => '1 месяц',
            'name_tk' => '1 aý',
            'description_ru' => 'Доступ на месяц',
            'description_tk' => 'Bir aýlyk giriş',
            'duration_days' => 30,
            'price' => 150,
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_subscriptions_page_requires_authentication(): void
    {
        $this->get(route('subscriptions.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_subscriptions_page(): void
    {
        $this->actingAsAdmin();
        SubscriptionPlan::factory()->count(3)->create();

        $this->get(route('subscriptions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Subscriptions/Index')
                ->has('plans.data', 3)
                ->has('subscriptions.data')
                ->has('stats')
            );
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_admin_can_create_a_plan(): void
    {
        $this->actingAsAdmin();

        $this->post(route('subscription-plans.store'), $this->validPayload())
            ->assertRedirect(route('subscriptions.index'))
            ->assertSessionHas('notification', fn ($notification) => $notification['type'] === 'success');

        $this->assertDatabaseHas('subscription_plans', [
            'name_ru' => '1 месяц',
            'name_tk' => '1 aý',
            'duration_days' => 30,
            'price' => 150,
            'is_active' => true,
        ]);
    }

    public function test_plan_creation_requires_both_languages(): void
    {
        $this->actingAsAdmin();

        $this->post(route('subscription-plans.store'), $this->validPayload(['name_tk' => '']))
            ->assertSessionHasErrors('name_tk');

        $this->assertDatabaseCount('subscription_plans', 0);
    }

    public function test_plan_duration_must_be_positive(): void
    {
        $this->actingAsAdmin();

        $this->post(route('subscription-plans.store'), $this->validPayload(['duration_days' => 0]))
            ->assertSessionHasErrors('duration_days');
    }

    public function test_plan_price_cannot_be_negative(): void
    {
        $this->actingAsAdmin();

        $this->post(route('subscription-plans.store'), $this->validPayload(['price' => -10]))
            ->assertSessionHasErrors('price');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_a_plan(): void
    {
        $this->actingAsAdmin();
        $plan = SubscriptionPlan::factory()->create();

        $this->put(route('subscription-plans.update', $plan), $this->validPayload(['price' => 199]))
            ->assertRedirect(route('subscriptions.index'));

        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id, 'price' => 199]);
    }

    public function test_editing_a_plan_does_not_rewrite_already_sold_subscriptions(): void
    {
        $this->actingAsAdmin();
        $plan = SubscriptionPlan::factory()->create(['name_ru' => 'Старое имя', 'price' => 100, 'duration_days' => 30]);
        $master = Master::factory()->create();

        $subscription = MasterSubscription::factory()->forMaster($master)->fromPlan($plan)->create();

        $this->put(route('subscription-plans.update', $plan), $this->validPayload([
            'name_ru' => 'Новое имя',
            'price' => 999,
            'duration_days' => 365,
        ]))->assertRedirect();

        $subscription->refresh();

        $this->assertSame('Старое имя', $subscription->plan_name);
        $this->assertEqualsWithDelta(100.0, (float) $subscription->price_paid, 0.01);
        $this->assertSame(30, $subscription->duration_days);
    }

    // ── Toggle ────────────────────────────────────────────────────────────────

    public function test_admin_can_toggle_plan_status(): void
    {
        $this->actingAsAdmin();
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

        $this->post(route('subscription-plans.toggle', $plan))->assertRedirect(route('subscriptions.index'));

        $this->assertFalse($plan->fresh()->is_active);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_deleting_a_plan_is_a_soft_delete_and_keeps_purchased_subscriptions(): void
    {
        $this->actingAsAdmin();
        $plan = SubscriptionPlan::factory()->create();
        $master = Master::factory()->create();
        $subscription = MasterSubscription::factory()->forMaster($master)->fromPlan($plan)->create();

        $this->delete(route('subscription-plans.destroy', $plan))->assertRedirect(route('subscriptions.index'));

        $this->assertSoftDeleted('subscription_plans', ['id' => $plan->id]);
        $this->assertDatabaseHas('master_subscriptions', [
            'id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'plan_name' => $plan->name_ru,
        ]);

        $this->assertNotNull($subscription->fresh()->plan);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_manager_cannot_view_subscriptions_page(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->get(route('subscriptions.index'))->assertForbidden();
    }

    public function test_manager_cannot_create_a_plan(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->post(route('subscription-plans.store'), $this->validPayload())->assertForbidden();

        $this->assertDatabaseCount('subscription_plans', 0);
    }

    public function test_operator_cannot_delete_a_plan(): void
    {
        $this->actingAs(User::factory()->operator()->create());
        $plan = SubscriptionPlan::factory()->create();

        $this->delete(route('subscription-plans.destroy', $plan))->assertForbidden();

        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id, 'deleted_at' => null]);
    }
}
