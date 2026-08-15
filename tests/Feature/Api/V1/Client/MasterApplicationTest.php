<?php

namespace Tests\Feature\Api\V1\Client;

use App\Enums\MasterStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Client;
use App\Models\Master;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Become a master": a signed-in client submits an application, an
 * administrator reviews it later. Submitting grants nothing on its own.
 */
class MasterApplicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private City $city;

    /** @var Collection<int, Category> */
    private Collection $categories;

    protected function setUp(): void
    {
        parent::setUp();

        $this->city = City::factory()->create();

        $parent = Category::factory()->create();
        $this->categories = Category::factory()->count(2)->child($parent)->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'city_id' => $this->city->id,
            'category_ids' => $this->categories->pluck('id')->all(),
            'experience_years' => 5,
            'about' => 'Кладу плитку 5 лет.',
        ], $overrides);
    }

    // ── submitting ────────────────────────────────────────────────────────────

    public function test_client_can_apply_to_become_a_master(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client, ['*']);

        $this->postJson(route('api.v1.client.master-application.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', MasterStatus::Pending->value)
            ->assertJsonPath('data.experience_years', 5)
            ->assertJsonPath('data.has_access', false)
            ->assertJsonCount(2, 'data.categories');

        $this->assertDatabaseHas('masters', [
            'client_id' => $client->id,
            'city_id' => $this->city->id,
            'status' => MasterStatus::Pending->value,
            'name' => $client->name,
            'phone' => $client->phone,
            'experience_years' => 5,
        ]);
    }

    public function test_a_fresh_application_does_not_open_the_master_api(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client, ['*']);

        $this->postJson(route('api.v1.client.master-application.store'), $this->payload())
            ->assertCreated();

        $this->getJson(route('api.v1.master.me'))
            ->assertForbidden()
            ->assertJsonPath('reason', 'application_pending');
    }

    public function test_application_requires_a_city_categories_and_experience(): void
    {
        Sanctum::actingAs(Client::factory()->create(), ['*']);

        $this->postJson(route('api.v1.client.master-application.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id', 'category_ids', 'experience_years']);
    }

    public function test_parent_categories_are_rejected(): void
    {
        Sanctum::actingAs(Client::factory()->create(), ['*']);

        $parent = Category::factory()->create();

        $this->postJson(
            route('api.v1.client.master-application.store'),
            $this->payload(['category_ids' => [$parent->id]])
        )->assertUnprocessable()->assertJsonValidationErrors('category_ids.0');
    }

    public function test_client_without_a_name_must_finish_their_profile_first(): void
    {
        Sanctum::actingAs(Client::factory()->create(['name' => null]), ['*']);

        $this->postJson(route('api.v1.client.master-application.store'), $this->payload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('masters', 0);
    }

    public function test_unauthenticated_client_cannot_apply(): void
    {
        $this->postJson(route('api.v1.client.master-application.store'), $this->payload())
            ->assertUnauthorized();
    }

    // ── re-applying ───────────────────────────────────────────────────────────

    public function test_client_cannot_apply_twice_while_under_review(): void
    {
        $master = Master::factory()->pending()->create();
        Sanctum::actingAs($master->client, ['*']);

        $this->postJson(route('api.v1.client.master-application.store'), $this->payload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('masters', 1);
    }

    public function test_approved_master_cannot_apply_again(): void
    {
        $master = Master::factory()->create();
        Sanctum::actingAs($master->client, ['*']);

        $this->postJson(route('api.v1.client.master-application.store'), $this->payload())
            ->assertUnprocessable();

        $this->assertSame(MasterStatus::Approved, $master->fresh()->status);
    }

    public function test_rejected_applicant_can_re_apply_on_the_same_profile(): void
    {
        $master = Master::factory()->rejected('Мало опыта')->create();
        Sanctum::actingAs($master->client, ['*']);

        $this->postJson(route('api.v1.client.master-application.store'), $this->payload(['experience_years' => 9]))
            ->assertCreated()
            ->assertJsonPath('data.id', $master->id)
            ->assertJsonPath('data.status', MasterStatus::Pending->value)
            ->assertJsonPath('data.rejection_reason', null);

        $this->assertDatabaseCount('masters', 1);
        $this->assertDatabaseHas('masters', [
            'id' => $master->id,
            'status' => MasterStatus::Pending->value,
            'experience_years' => 9,
            'rejection_reason' => null,
            'reviewed_at' => null,
        ]);
    }

    // ── reading the verdict ───────────────────────────────────────────────────

    public function test_client_who_never_applied_reads_a_null_application(): void
    {
        Sanctum::actingAs(Client::factory()->create(), ['*']);

        $this->getJson(route('api.v1.client.master-application.show'))
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_applicant_can_follow_their_application(): void
    {
        $master = Master::factory()->rejected('Мало опыта')->create();
        Sanctum::actingAs($master->client, ['*']);

        $this->getJson(route('api.v1.client.master-application.show'))
            ->assertOk()
            ->assertJsonPath('data.status', MasterStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'Мало опыта')
            ->assertJsonPath('data.has_access', false);
    }

    public function test_approved_and_subscribed_master_reads_has_access(): void
    {
        $master = Master::factory()->create();
        Sanctum::actingAs($master->client, ['*']);

        $this->getJson(route('api.v1.client.master-application.show'))
            ->assertOk()
            ->assertJsonPath('data.status', MasterStatus::Approved->value)
            ->assertJsonPath('data.has_access', true);
    }

    // ── the role is visible on the client profile ─────────────────────────────

    public function test_profile_of_a_plain_client_carries_no_master_role(): void
    {
        Sanctum::actingAs(Client::factory()->create(), ['*']);

        $this->getJson(route('api.v1.client.me'))
            ->assertOk()
            ->assertJsonPath('data.master_status', null)
            ->assertJsonPath('data.has_master_access', false);
    }

    public function test_profile_of_a_working_master_carries_the_role(): void
    {
        $master = Master::factory()->create();
        Sanctum::actingAs($master->client, ['*']);

        $this->getJson(route('api.v1.client.me'))
            ->assertOk()
            ->assertJsonPath('data.master_status', MasterStatus::Approved->value)
            ->assertJsonPath('data.master_id', $master->id)
            ->assertJsonPath('data.has_master_access', true);
    }
}
