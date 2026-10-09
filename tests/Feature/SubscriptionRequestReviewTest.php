<?php

namespace Tests\Feature;

use App\Enums\SubscriptionRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Notifications\Push\SubscriptionRequestApprovedNotification;
use App\Notifications\Push\SubscriptionRequestRejectedNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Admin side of the app's "Buy" button: the owner settles the payment with the
 * master, then approves (selling the plan) or rejects the request.
 */
class SubscriptionRequestReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->administrator()->create();
        $this->actingAs($user);

        return $user;
    }

    private function requestFromMaster(Master $master, ?SubscriptionPlan $plan = null): SubscriptionRequest
    {
        return SubscriptionRequest::factory()
            ->forClient($master->client)
            ->forPlan($plan ?? SubscriptionPlan::factory()->days(30)->create(['price' => 150]))
            ->create();
    }

    // ── The queue ─────────────────────────────────────────────────────────────

    public function test_the_queue_shows_pending_requests_by_default(): void
    {
        $this->actingAsAdmin();
        $pending = SubscriptionRequest::factory()->create();
        SubscriptionRequest::factory()->approved()->create();
        SubscriptionRequest::factory()->rejected()->create();

        $this->get(route('subscription-requests.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Subscriptions/Requests')
                ->has('requests.data', 1)
                ->where('requests.data.0.id', $pending->id)
                ->where('filters.status', 'pending'));
    }

    public function test_the_queue_can_be_filtered_and_shown_in_full(): void
    {
        $this->actingAsAdmin();
        SubscriptionRequest::factory()->create();
        $rejected = SubscriptionRequest::factory()->rejected()->create();

        $this->get(route('subscription-requests.index', ['status' => 'rejected']))
            ->assertInertia(fn ($page) => $page
                ->has('requests.data', 1)
                ->where('requests.data.0.id', $rejected->id));

        $this->get(route('subscription-requests.index', ['status' => 'all']))
            ->assertInertia(fn ($page) => $page
                ->has('requests.data', 2)
                ->where('filters.status', 'all'));
    }

    public function test_the_queue_flags_requests_that_cannot_be_approved(): void
    {
        $this->actingAsAdmin();
        SubscriptionRequest::factory()->create();

        $this->get(route('subscription-requests.index'))
            ->assertInertia(fn ($page) => $page
                ->where('requests.data.0.master_status', null)
                ->where('requests.data.0.can_be_approved', false));
    }

    public function test_pending_count_is_shared_with_administrators(): void
    {
        $this->actingAsAdmin();
        SubscriptionRequest::factory()->count(2)->create();
        SubscriptionRequest::factory()->approved()->create();

        $this->get(route('subscription-requests.index'))
            ->assertInertia(fn ($page) => $page->where('pendingSubscriptionRequestCount', 2));
    }

    public function test_managers_cannot_open_the_queue(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->get(route('subscription-requests.index'))->assertForbidden();
    }

    public function test_guests_cannot_open_the_queue(): void
    {
        $this->get(route('subscription-requests.index'))->assertRedirect(route('login'));
    }

    // ── Approving ─────────────────────────────────────────────────────────────

    public function test_approving_opens_access_for_a_master_without_a_subscription(): void
    {
        $admin = $this->actingAsAdmin();
        $master = Master::factory()->expired()->create();
        $request = $this->requestFromMaster($master);

        $this->post(route('subscription-requests.approve', $request), ['note' => 'Наличные'])
            ->assertRedirect()
            ->assertSessionHas('notification.type', 'success');

        $request->refresh();
        $subscription = MasterSubscription::firstOrFail();

        $this->assertSame(SubscriptionRequestStatus::Approved, $request->status);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);
        $this->assertSame($subscription->id, $request->master_subscription_id);

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($master->id, $subscription->master_id);
        $this->assertSame(150.0, (float) $subscription->price_paid);
        $this->assertSame('Наличные', $subscription->note);
        $this->assertTrue($master->refresh()->hasActiveAccess());
    }

    public function test_approving_for_a_running_subscription_queues_a_renewal(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create(['access_expires_at' => now()->addDays(10)]);
        $running = MasterSubscription::factory()->forMaster($master)->create(['expires_at' => now()->addDays(10)]);
        $request = $this->requestFromMaster($master);

        $this->post(route('subscription-requests.approve', $request));

        $renewal = MasterSubscription::whereKeyNot($running->id)->firstOrFail();

        $this->assertSame(SubscriptionStatus::Pending, $renewal->status);
        $this->assertTrue($renewal->starts_at->equalTo($running->expires_at));
        $this->assertTrue($master->refresh()->access_expires_at->equalTo($running->expires_at->copy()->addDays(30)));
    }

    public function test_approving_takes_the_price_actually_paid(): void
    {
        $this->actingAsAdmin();
        $request = $this->requestFromMaster(Master::factory()->create());

        $this->post(route('subscription-requests.approve', $request), ['price_paid' => 0]);

        $this->assertSame(0.0, (float) MasterSubscription::firstOrFail()->price_paid);
    }

    public function test_approving_pushes_the_requester(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $request = $this->requestFromMaster($master);

        $this->post(route('subscription-requests.approve', $request));

        Notification::assertSentTo(
            $master->client,
            SubscriptionRequestApprovedNotification::class,
            fn (SubscriptionRequestApprovedNotification $notification) => $notification->request->is($request),
        );
    }

    public function test_approving_a_client_without_a_master_profile_fails(): void
    {
        $this->actingAsAdmin();
        $request = SubscriptionRequest::factory()->forClient(Client::factory()->create())->create();

        $this->post(route('subscription-requests.approve', $request))
            ->assertSessionHas('notification.message', __('subscription_requests.errors.not_a_master'));

        $this->assertSame(SubscriptionRequestStatus::Pending, $request->refresh()->status);
        $this->assertDatabaseCount('master_subscriptions', 0);
        Notification::assertNothingSent();
    }

    public function test_approving_a_master_still_under_review_fails(): void
    {
        $this->actingAsAdmin();
        $request = $this->requestFromMaster(Master::factory()->pending()->create());

        $this->post(route('subscription-requests.approve', $request))
            ->assertSessionHas('notification.type', 'error');

        $this->assertSame(SubscriptionRequestStatus::Pending, $request->refresh()->status);
        $this->assertDatabaseCount('master_subscriptions', 0);
    }

    public function test_approving_a_disabled_plan_fails(): void
    {
        $this->actingAsAdmin();
        $request = $this->requestFromMaster(Master::factory()->create(), SubscriptionPlan::factory()->inactive()->create());

        $this->post(route('subscription-requests.approve', $request))
            ->assertSessionHas('notification.message', __('subscriptions.errors.plan_not_available'));

        $this->assertSame(SubscriptionRequestStatus::Pending, $request->refresh()->status);
        $this->assertDatabaseCount('master_subscriptions', 0);
    }

    public function test_a_reviewed_request_cannot_be_approved_again(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $request = SubscriptionRequest::factory()->forClient($master->client)->rejected()->create();

        $this->post(route('subscription-requests.approve', $request))
            ->assertSessionHas('notification.message', __('subscription_requests.errors.not_pending'));

        $this->assertSame(SubscriptionRequestStatus::Rejected, $request->refresh()->status);
        $this->assertDatabaseCount('master_subscriptions', 0);
    }

    public function test_approving_rejects_a_negative_price(): void
    {
        $this->actingAsAdmin();
        $request = $this->requestFromMaster(Master::factory()->create());

        $this->post(route('subscription-requests.approve', $request), ['price_paid' => -1])
            ->assertSessionHasErrors('price_paid');

        $this->assertSame(SubscriptionRequestStatus::Pending, $request->refresh()->status);
    }

    public function test_managers_cannot_approve(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $request = $this->requestFromMaster(Master::factory()->create());

        $this->post(route('subscription-requests.approve', $request))->assertForbidden();

        $this->assertSame(SubscriptionRequestStatus::Pending, $request->refresh()->status);
    }

    // ── Rejecting ─────────────────────────────────────────────────────────────

    public function test_rejecting_records_the_reason_and_pushes_the_requester(): void
    {
        $admin = $this->actingAsAdmin();
        $master = Master::factory()->create();
        $request = $this->requestFromMaster($master);

        $this->post(route('subscription-requests.reject', $request), ['rejection_reason' => 'Оплата не поступила'])
            ->assertSessionHas('notification.type', 'success');

        $request->refresh();

        $this->assertSame(SubscriptionRequestStatus::Rejected, $request->status);
        $this->assertSame('Оплата не поступила', $request->rejection_reason);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertDatabaseCount('master_subscriptions', 0);

        Notification::assertSentTo($master->client, SubscriptionRequestRejectedNotification::class);
    }

    public function test_rejecting_without_a_reason_is_allowed(): void
    {
        $this->actingAsAdmin();
        $request = SubscriptionRequest::factory()->create();

        $this->post(route('subscription-requests.reject', $request))
            ->assertSessionHasNoErrors();

        $this->assertSame(SubscriptionRequestStatus::Rejected, $request->refresh()->status);
        $this->assertNull($request->rejection_reason);
    }

    public function test_a_reviewed_request_cannot_be_rejected(): void
    {
        $this->actingAsAdmin();
        $request = SubscriptionRequest::factory()->approved()->create();

        $this->post(route('subscription-requests.reject', $request))
            ->assertSessionHas('notification.message', __('subscription_requests.errors.not_pending'));

        $this->assertSame(SubscriptionRequestStatus::Approved, $request->refresh()->status);
        Notification::assertNothingSent();
    }

    // ── Push payload ──────────────────────────────────────────────────────────

    public function test_approved_push_names_the_plan_and_the_new_deadline(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->expired()->create();
        $request = $this->requestFromMaster($master, SubscriptionPlan::factory()->days(30)->create(['name_ru' => 'Месяц']));

        $this->post(route('subscription-requests.approve', $request));

        Notification::assertSentTo($master->client, function (SubscriptionRequestApprovedNotification $notification) use ($master) {
            $message = $notification->toFcm($master->client)->jsonSerialize();

            return $message['data']['type'] === 'subscription_request.approved'
                && $message['data']['audience'] === 'master'
                && str_contains($message['notification']['body'], 'Месяц')
                && str_contains($message['notification']['body'], now()->addDays(30)->format('d.m.Y'));
        });
    }

    public function test_rejected_push_carries_the_reason(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $request = $this->requestFromMaster($master);

        $this->post(route('subscription-requests.reject', $request), ['rejection_reason' => 'Оплата не поступила']);

        Notification::assertSentTo($master->client, function (SubscriptionRequestRejectedNotification $notification) use ($master) {
            $message = $notification->toFcm($master->client)->jsonSerialize();

            return $message['data']['type'] === 'subscription_request.rejected'
                && $message['notification']['body'] === 'Оплата не поступила';
        });
    }
}
