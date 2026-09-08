<?php

namespace Tests\Feature;

use App\Enums\MasterStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Admin side of "become a master": the owner takes the payment in person, then
 * approves the application and dials in the interval that was paid for.
 */
class MasterApplicationReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── The queue ─────────────────────────────────────────────────────────────

    public function test_the_queue_lists_only_applications_under_review(): void
    {
        $this->actingAsAdmin();

        $pending = Master::factory()->pending()->create();
        Master::factory()->create();
        Master::factory()->rejected()->create();

        $this->get(route('master-applications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masters/Applications')
                ->has('applications.data', 1)
                ->where('applications.data.0.id', $pending->id));
    }

    public function test_guests_cannot_open_the_queue(): void
    {
        $this->get(route('master-applications.index'))->assertRedirect(route('login'));
    }

    // ── Approving ─────────────────────────────────────────────────────────────

    public function test_approving_with_a_plan_opens_access(): void
    {
        $admin = $this->actingAsAdmin();
        $master = Master::factory()->pending()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create(['price' => 150]);

        $this->post(route('master-applications.approve', $master), [
            'subscription_plan_id' => $plan->id,
            'subscription_note' => 'Оплата наличными',
        ])->assertRedirect(route('master-applications.index'));

        $master->refresh();

        $this->assertSame(MasterStatus::Approved, $master->status);
        $this->assertSame($admin->id, $master->reviewed_by);
        $this->assertNotNull($master->reviewed_at);
        $this->assertTrue($master->hasActiveAccess());

        $subscription = MasterSubscription::firstOrFail();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($master->id, $subscription->master_id);
    }

    public function test_approving_without_a_plan_is_rejected(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->pending()->create();

        $this->post(route('master-applications.approve', $master))
            ->assertSessionHasErrors('subscription_plan_id');

        $master->refresh();

        $this->assertSame(MasterStatus::Pending, $master->status);
        $this->assertNull($master->reviewed_by);
        $this->assertDatabaseCount('master_subscriptions', 0);
    }

    public function test_an_approved_and_subscribed_master_reaches_the_master_api(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->pending()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('master-applications.approve', $master), [
            'subscription_plan_id' => $plan->id,
        ]);

        // Drop the admin's web session: Sanctum resolves that guard first and
        // would answer as the administrator instead of the token's client.
        auth()->guard('web')->logout();
        $this->flushSession();

        $token = $master->client->createToken('mobile-client')->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.master.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $master->id);
    }

    public function test_an_already_approved_master_cannot_be_approved_again(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('master-applications.approve', $master), [
            'subscription_plan_id' => $plan->id,
        ])->assertRedirect(route('master-applications.index'));

        $this->assertDatabaseCount('master_subscriptions', 0);
        $this->assertNull($master->fresh()->reviewed_by);
    }

    public function test_a_rejected_application_cannot_be_approved_directly(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->rejected()->create();
        $plan = SubscriptionPlan::factory()->days(30)->create();

        $this->post(route('master-applications.approve', $master), [
            'subscription_plan_id' => $plan->id,
        ])->assertRedirect(route('master-applications.index'));

        $this->assertSame(MasterStatus::Rejected, $master->fresh()->status);
    }

    // ── Rejecting ─────────────────────────────────────────────────────────────

    public function test_rejecting_stores_the_reason(): void
    {
        $admin = $this->actingAsAdmin();
        $master = Master::factory()->pending()->create();

        $this->post(route('master-applications.reject', $master), [
            'rejection_reason' => 'Нет опыта по указанным категориям',
        ])->assertRedirect(route('master-applications.index'));

        $master->refresh();

        $this->assertSame(MasterStatus::Rejected, $master->status);
        $this->assertSame('Нет опыта по указанным категориям', $master->rejection_reason);
        $this->assertSame($admin->id, $master->reviewed_by);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->pending()->create();

        $this->post(route('master-applications.reject', $master), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(MasterStatus::Pending, $master->fresh()->status);
    }

    public function test_rejecting_takes_the_master_off_the_available_list(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->pending()->create(['is_available' => true]);

        $this->post(route('master-applications.reject', $master), [
            'rejection_reason' => 'Не подходит',
        ]);

        $this->assertFalse($master->fresh()->is_available);
    }
}
