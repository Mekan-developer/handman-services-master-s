<?php

namespace Tests\Feature\Api\V1;

use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MasterSubscriptionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Master endpoints are reached with the client token the profile hangs off. */
    private function actingAsMaster(Master $master): Master
    {
        $this->actingAs($master->client, 'sanctum');

        return $master;
    }

    // ── Plans (public price list) ─────────────────────────────────────────────

    public function test_plans_are_readable_without_a_token(): void
    {
        SubscriptionPlan::factory()->days(30)->create(['price' => 150]);

        $this->getJson(route('api.v1.master.subscription-plans'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'duration_days', 'price']]]);
    }

    public function test_master_with_expired_access_can_still_see_the_plans(): void
    {
        $this->actingAsMaster(Master::factory()->expired()->create());
        SubscriptionPlan::factory()->count(2)->create();

        $this->getJson(route('api.v1.master.subscription-plans'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_inactive_plans_are_hidden_from_the_price_list(): void
    {
        SubscriptionPlan::factory()->create();
        SubscriptionPlan::factory()->inactive()->create();

        $this->getJson(route('api.v1.master.subscription-plans'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── Own subscription ──────────────────────────────────────────────────────

    public function test_master_can_read_their_current_subscription(): void
    {
        $master = $this->actingAsMaster(Master::factory()->create());
        MasterSubscription::factory()->forMaster($master)->create(['plan_name' => '1 месяц']);

        $this->getJson(route('api.v1.master.subscription'))
            ->assertOk()
            ->assertJsonPath('data.current.plan_name', '1 месяц')
            ->assertJsonPath('data.current.status', 'active')
            ->assertJsonPath('data.has_active_access', true);
    }

    public function test_master_with_expired_access_can_still_read_their_subscription(): void
    {
        $master = $this->actingAsMaster(Master::factory()->expired()->create());
        MasterSubscription::factory()->forMaster($master)->expired()->create();

        $this->getJson(route('api.v1.master.subscription'))
            ->assertOk()
            ->assertJsonPath('data.current', null)
            ->assertJsonPath('data.has_active_access', false)
            ->assertJsonCount(1, 'data.history');
    }

    public function test_master_never_sees_another_masters_subscription(): void
    {
        $master = $this->actingAsMaster(Master::factory()->create());
        MasterSubscription::factory()->forMaster($master)->create(['plan_name' => 'Моя']);
        MasterSubscription::factory()->forMaster(Master::factory()->create())->create(['plan_name' => 'Чужая']);

        $this->getJson(route('api.v1.master.subscription'))
            ->assertOk()
            ->assertJsonCount(1, 'data.history')
            ->assertJsonPath('data.history.0.plan_name', 'Моя');
    }

    public function test_subscription_endpoint_requires_authentication(): void
    {
        $this->getJson(route('api.v1.master.subscription'))->assertUnauthorized();
    }

    public function test_blocked_master_cannot_read_their_subscription(): void
    {
        $this->actingAsMaster(Master::factory()->inactive()->create());

        $this->getJson(route('api.v1.master.subscription'))->assertForbidden();
    }

    // ── Regression: the strict gate still blocks elsewhere ────────────────────

    public function test_expired_master_is_still_blocked_from_the_ordinary_master_endpoints(): void
    {
        $this->actingAsMaster(Master::factory()->expired()->create());

        $this->getJson(route('api.v1.master.me'))->assertForbidden();
    }
}
