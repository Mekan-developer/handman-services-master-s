<?php

namespace Tests\Feature\Api\V1;

use App\Models\Client;
use App\Models\Master;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * There is no separate master login: the mobile app signs in as a client and
 * the very same token opens the master endpoints once the account carries an
 * approved, subscribed master profile. These tests cover that gate.
 */
class MasterAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function tokenFor(Client $client): string
    {
        return $client->createToken('mobile-client')->plainTextToken;
    }

    // ── who gets through ──────────────────────────────────────────────────────

    public function test_approved_master_reaches_the_master_api_with_their_client_token(): void
    {
        $master = Master::factory()->create();

        $this->withToken($this->tokenFor($master->client))
            ->getJson(route('api.v1.master.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $master->id);
    }

    public function test_client_without_a_master_profile_is_told_they_never_applied(): void
    {
        $client = Client::factory()->create();

        $this->withToken($this->tokenFor($client))
            ->getJson(route('api.v1.master.me'))
            ->assertForbidden()
            ->assertJsonPath('reason', 'not_a_master');
    }

    public function test_application_under_review_cannot_work_yet(): void
    {
        $master = Master::factory()->pending()->create();

        $this->withToken($this->tokenFor($master->client))
            ->getJson(route('api.v1.master.me'))
            ->assertForbidden()
            ->assertJsonPath('reason', 'application_pending');
    }

    public function test_rejected_application_cannot_work(): void
    {
        $master = Master::factory()->rejected()->create();

        $this->withToken($this->tokenFor($master->client))
            ->getJson(route('api.v1.master.me'))
            ->assertForbidden()
            ->assertJsonPath('reason', 'application_rejected');
    }

    public function test_deactivated_master_is_rejected(): void
    {
        $master = Master::factory()->create();
        $token = $this->tokenFor($master->client);

        $master->update(['is_active' => false]);

        $this->withToken($token)
            ->getJson(route('api.v1.master.me'))
            ->assertForbidden()
            ->assertJsonPath('reason', 'disabled');
    }

    public function test_master_with_a_lapsed_subscription_is_rejected(): void
    {
        $master = Master::factory()->create();
        $token = $this->tokenFor($master->client);

        $master->update(['access_expires_at' => now()->subDay()]);

        $this->withToken($token)
            ->getJson(route('api.v1.master.me'))
            ->assertForbidden()
            ->assertJsonPath('reason', 'access_expired');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson(route('api.v1.master.me'))->assertUnauthorized();
    }

    // ── losing the master role leaves the client account intact ───────────────

    public function test_deactivated_master_can_still_use_the_client_api(): void
    {
        $master = Master::factory()->create();
        $token = $this->tokenFor($master->client);

        $master->update(['is_active' => false]);

        $this->withToken($token)
            ->getJson(route('api.v1.client.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $master->client_id);
    }

    public function test_deactivating_a_master_takes_them_off_the_available_list(): void
    {
        $master = Master::factory()->create(['is_available' => true]);

        $master->update(['is_active' => false]);

        $this->assertFalse($master->fresh()->is_available);
    }
}
