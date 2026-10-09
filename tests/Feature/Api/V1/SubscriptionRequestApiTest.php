<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SubscriptionRequestStatus;
use App\Models\Client;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * "Buy" on the app's plans screen — files a request for the administrator.
 */
class SubscriptionRequestApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsClient(?Client $client = null): Client
    {
        $client ??= Client::factory()->create();
        $this->actingAs($client, 'sanctum');

        return $client;
    }

    // ── Submitting ────────────────────────────────────────────────────────────

    public function test_master_can_request_a_plan(): void
    {
        $master = Master::factory()->create();
        $this->actingAsClient($master->client);
        $plan = SubscriptionPlan::factory()->days(30)->create(['name_ru' => 'Месяц', 'price' => 1250]);

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('message', __('api.subscription_request.submitted'))
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.plan_name', 'Месяц')
            ->assertJsonPath('data.price', 1250)
            ->assertJsonPath('data.duration_days', 30)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Ожидает')
            ->assertJsonPath('data.rejection_reason', null)
            ->assertJsonStructure(['message', 'data' => ['id', 'created_at']]);

        $this->assertDatabaseHas('subscription_requests', [
            'client_id' => $master->client_id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionRequestStatus::Pending->value,
        ]);
    }

    public function test_master_with_expired_access_can_request_a_renewal(): void
    {
        $master = Master::factory()->expired()->create();
        $this->actingAsClient($master->client);
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertCreated();
    }

    public function test_client_without_a_master_profile_can_request_a_plan(): void
    {
        $client = $this->actingAsClient();
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertCreated();

        $this->assertDatabaseHas('subscription_requests', ['client_id' => $client->id]);
    }

    public function test_message_and_labels_follow_the_requested_locale(): void
    {
        $this->actingAsClient();
        $plan = SubscriptionPlan::factory()->create(['name_ru' => 'Месяц', 'name_tk' => 'Aý']);

        $this->withHeader('X-Locale', 'tk')
            ->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('message', 'Arza iberildi. Administrator siziň bilen habarlaşar')
            ->assertJsonPath('data.plan_name', 'Aý')
            ->assertJsonPath('data.status_label', 'Garaşýar');
    }

    public function test_a_second_request_while_one_is_pending_is_a_conflict(): void
    {
        $client = $this->actingAsClient();
        SubscriptionRequest::factory()->forClient($client)->create();
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertStatus(409)
            ->assertJsonPath('message', __('api.subscription_request.already_pending'));

        $this->assertDatabaseCount('subscription_requests', 1);
    }

    public function test_another_clients_pending_request_does_not_block(): void
    {
        SubscriptionRequest::factory()->create();
        $this->actingAsClient();
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertCreated();
    }

    public function test_client_can_request_again_once_the_previous_one_is_reviewed(): void
    {
        $client = $this->actingAsClient();
        SubscriptionRequest::factory()->forClient($client)->rejected()->create();
        SubscriptionRequest::factory()->forClient($client)->approved()->create();
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertCreated();
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_plan_id_is_required(): void
    {
        $this->actingAsClient();

        $this->postJson(route('api.v1.master.subscription-requests.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_plan_id_must_be_an_integer(): void
    {
        $this->actingAsClient();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_unknown_plan_is_rejected(): void
    {
        $this->actingAsClient();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_disabled_plan_is_rejected(): void
    {
        $this->actingAsClient();
        $plan = SubscriptionPlan::factory()->inactive()->create();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_deleted_plan_is_rejected(): void
    {
        $this->actingAsClient();
        $plan = SubscriptionPlan::factory()->create();
        $plan->delete();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');

        $this->assertDatabaseCount('subscription_requests', 0);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_guest_gets_401(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson(route('api.v1.master.subscription-requests.store'), ['plan_id' => $plan->id])
            ->assertUnauthorized();
        $this->getJson(route('api.v1.master.subscription-requests.index'))
            ->assertUnauthorized();
    }

    // ── Reading back ──────────────────────────────────────────────────────────

    public function test_client_sees_only_their_own_requests_newest_first(): void
    {
        $client = $this->actingAsClient();
        $older = SubscriptionRequest::factory()->forClient($client)->rejected('Нет оплаты')->create();
        $newer = SubscriptionRequest::factory()->forClient($client)->create();
        SubscriptionRequest::factory()->create();

        $this->getJson(route('api.v1.master.subscription-requests.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.status', 'rejected')
            ->assertJsonPath('data.1.rejection_reason', 'Нет оплаты');
    }

    public function test_client_without_requests_gets_an_empty_list(): void
    {
        $this->actingAsClient();

        $this->getJson(route('api.v1.master.subscription-requests.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_master_subscription_carries_the_pending_request(): void
    {
        $master = Master::factory()->expired()->create();
        $this->actingAsClient($master->client);
        $pending = SubscriptionRequest::factory()->forClient($master->client)->create();

        $this->getJson(route('api.v1.master.subscription'))
            ->assertOk()
            ->assertJsonPath('data.pending_request.id', $pending->id)
            ->assertJsonPath('data.pending_request.status', 'pending');
    }

    public function test_master_subscription_has_no_pending_request_once_reviewed(): void
    {
        $master = Master::factory()->create();
        $this->actingAsClient($master->client);
        MasterSubscription::factory()->forMaster($master)->create();
        SubscriptionRequest::factory()->forClient($master->client)->approved()->create();

        $this->getJson(route('api.v1.master.subscription'))
            ->assertOk()
            ->assertJsonPath('data.pending_request', null);
    }
}
