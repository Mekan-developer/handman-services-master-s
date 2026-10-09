<?php

namespace Tests\Feature;

use App\Events\SubscriptionRequestSubmitted;
use App\Listeners\NotifyAdminsOnNewSubscriptionRequest;
use App\Models\Client;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Notifications\NewSubscriptionRequestNotification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A plan request from the app must reach the administrators right away —
 * a bell entry plus a live toast — instead of waiting for a page reload.
 */
class NotifyAdminsOnNewSubscriptionRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function submittedEvent(): SubscriptionRequestSubmitted
    {
        $client = Client::factory()->create(['name' => 'Мекан']);
        $plan = SubscriptionPlan::factory()->create(['name_ru' => 'Месяц', 'name_tk' => 'Aý']);
        $request = SubscriptionRequest::factory()->forClient($client)->forPlan($plan)->create();

        return new SubscriptionRequestSubmitted($request->load('client', 'plan'));
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    public function test_submitting_a_request_broadcasts_to_the_admin_channel(): void
    {
        Event::fake([SubscriptionRequestSubmitted::class]);
        $client = Client::factory()->create(['name' => 'Мекан']);
        $this->actingAs($client, 'sanctum');
        $plan = SubscriptionPlan::factory()->create(['name_ru' => 'Месяц', 'name_tk' => 'Aý']);

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertCreated();

        Event::assertDispatched(SubscriptionRequestSubmitted::class, function (SubscriptionRequestSubmitted $event) {
            return $event->broadcastOn()[0]->name === 'private-admin.subscription-requests'
                && $event->broadcastAs() === 'subscription-request.submitted'
                && $event->payload['client_name'] === 'Мекан'
                && $event->payload['plan_name_ru'] === 'Месяц'
                && $event->payload['plan_name_tk'] === 'Aý';
        });
    }

    public function test_a_rejected_duplicate_request_broadcasts_nothing(): void
    {
        Event::fake([SubscriptionRequestSubmitted::class]);
        $client = Client::factory()->create();
        $this->actingAs($client, 'sanctum');
        SubscriptionRequest::factory()->forClient($client)->create();

        $this->postJson(route('api.v1.master.subscription-requests.store'), [
            'plan_id' => SubscriptionPlan::factory()->create()->id,
        ])->assertStatus(409);

        Event::assertNotDispatched(SubscriptionRequestSubmitted::class);
    }

    public function test_broadcast_is_queued_so_the_api_does_not_wait_on_reverb(): void
    {
        $event = $this->submittedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertNotInstanceOf(ShouldBroadcastNow::class, $event);
    }

    // ── Bell notification ─────────────────────────────────────────────────────

    public function test_only_administrators_are_notified(): void
    {
        Notification::fake();
        $administrator = User::factory()->administrator()->create();
        $manager = User::factory()->manager()->create();
        $operator = User::factory()->operator()->create();

        app(NotifyAdminsOnNewSubscriptionRequest::class)->handle($this->submittedEvent());

        Notification::assertSentTo($administrator, NewSubscriptionRequestNotification::class);
        Notification::assertNotSentTo($manager, NewSubscriptionRequestNotification::class);
        Notification::assertNotSentTo($operator, NewSubscriptionRequestNotification::class);
    }

    public function test_every_administrator_receives_the_notification(): void
    {
        Notification::fake();
        User::factory()->count(3)->administrator()->create();

        app(NotifyAdminsOnNewSubscriptionRequest::class)->handle($this->submittedEvent());

        Notification::assertSentTimes(NewSubscriptionRequestNotification::class, 3);
    }

    public function test_bell_entry_carries_what_the_panel_shows(): void
    {
        $administrator = User::factory()->administrator()->create();
        $event = $this->submittedEvent();

        app(NotifyAdminsOnNewSubscriptionRequest::class)->handle($event);

        $data = $administrator->notifications()->firstOrFail()->data;

        $this->assertSame('new_subscription_request', $data['type']);
        $this->assertSame($event->payload['id'], $data['subscription_request_id']);
        $this->assertSame('Мекан', $data['client_name']);
        $this->assertSame('Месяц', $data['plan_name_ru']);
        $this->assertSame('Aý', $data['plan_name_tk']);
    }

    // ── Channel ───────────────────────────────────────────────────────────────

    public function test_only_administrators_may_subscribe_to_the_channel(): void
    {
        // The test env uses the null broadcaster, which authorizes everything.
        // Swap in a real one so the channel callback actually decides.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $payload = ['channel_name' => 'private-admin.subscription-requests', 'socket_id' => '123.456'];

        $this->actingAs(User::factory()->administrator()->create())
            ->postJson('/broadcasting/auth', $payload)
            ->assertOk();

        $this->actingAs(User::factory()->manager()->create())
            ->postJson('/broadcasting/auth', $payload)
            ->assertForbidden();
    }
}
