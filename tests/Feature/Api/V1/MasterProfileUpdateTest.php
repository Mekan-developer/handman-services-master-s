<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\City;
use App\Models\Client;
use App\Models\Master;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A working master maintains their own trade details — city, categories,
 * experience, about — without going through an administrator.
 */
class MasterProfileUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function tokenFor(Client $client): string
    {
        return $client->createToken('mobile-client')->plainTextToken;
    }

    /** @return array<int, Category> */
    private function leafCategories(int $count = 2): array
    {
        $parent = Category::factory()->create();

        return Category::factory()->count($count)->child($parent)->create()->all();
    }

    // ── editing ───────────────────────────────────────────────────────────────

    public function test_master_can_update_experience_and_about(): void
    {
        $master = Master::factory()->create(['experience_years' => 3, 'about' => 'Старое описание']);

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), [
                'experience_years' => 11,
                'about' => 'Кладу плитку и штукатурю.',
            ])
            ->assertOk()
            ->assertJsonPath('data.experience_years', 11)
            ->assertJsonPath('data.about', 'Кладу плитку и штукатурю.');

        $this->assertDatabaseHas('masters', [
            'id' => $master->id,
            'experience_years' => 11,
            'about' => 'Кладу плитку и штукатурю.',
        ]);
    }

    public function test_master_can_replace_their_categories(): void
    {
        $master = Master::factory()->create();
        [$old] = $this->leafCategories(1);
        $master->categories()->sync([$old->id]);

        [$first, $second] = $this->leafCategories();

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), [
                'category_ids' => [$first->id, $second->id],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.categories');

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $master->fresh()->categories->pluck('id')->all()
        );
    }

    public function test_updating_one_field_keeps_the_categories(): void
    {
        $master = Master::factory()->create();
        $categories = $this->leafCategories();
        $master->categories()->sync(collect($categories)->pluck('id')->all());

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), ['experience_years' => 7])
            ->assertOk()
            ->assertJsonCount(2, 'data.categories');

        $this->assertCount(2, $master->fresh()->categories);
    }

    public function test_about_can_be_cleared(): void
    {
        $master = Master::factory()->create(['about' => 'Есть текст']);

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), ['about' => null])
            ->assertOk()
            ->assertJsonPath('data.about', null);

        $this->assertDatabaseHas('masters', ['id' => $master->id, 'about' => null]);
    }

    // ── validation ────────────────────────────────────────────────────────────

    public function test_parent_categories_are_rejected(): void
    {
        $master = Master::factory()->create();
        $parent = Category::factory()->create();

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), ['category_ids' => [$parent->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids.0');
    }

    public function test_category_list_cannot_be_emptied(): void
    {
        $master = Master::factory()->create();
        $categories = $this->leafCategories();
        $master->categories()->sync(collect($categories)->pluck('id')->all());

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), ['category_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_ids');

        $this->assertCount(2, $master->fresh()->categories);
    }

    public function test_absurd_experience_is_rejected(): void
    {
        $master = Master::factory()->create();

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), ['experience_years' => 120])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('experience_years');
    }

    // ── what the master may not touch ─────────────────────────────────────────

    public function test_status_activity_and_access_deadline_are_not_writable(): void
    {
        $master = Master::factory()->create(['access_expires_at' => now()->addDays(3)]);
        $expiresAt = $master->access_expires_at;

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), [
                'experience_years' => 4,
                'is_active' => false,
                'status' => 'rejected',
                'access_expires_at' => now()->addYears(5)->toDateTimeString(),
                'name' => 'Чужое имя',
                'phone' => '+99361000000',
            ])
            ->assertOk();

        $fresh = $master->fresh();

        $this->assertTrue($fresh->is_active);
        $this->assertTrue($fresh->isApproved());
        $this->assertTrue($expiresAt->equalTo($fresh->access_expires_at));
        $this->assertSame($master->name, $fresh->name);
        $this->assertSame($master->phone, $fresh->phone);
    }

    /** The city is one per person and lives on the client account. */
    public function test_city_cannot_be_changed_here(): void
    {
        $master = Master::factory()->create();
        $other = City::factory()->create();

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), ['city_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.city.id', $master->city_id);

        $this->assertSame($master->city_id, $master->fresh()->city_id);
    }

    public function test_changing_the_city_on_the_client_account_moves_the_master_too(): void
    {
        $master = Master::factory()->create();
        $city = City::factory()->create();

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.client.me.update'), ['city_id' => $city->id])
            ->assertOk();

        $this->assertSame($city->id, $master->fresh()->city_id);
    }

    // ── the gate ──────────────────────────────────────────────────────────────

    public function test_applicant_under_review_cannot_edit_the_master_profile(): void
    {
        $master = Master::factory()->pending()->create();

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), ['experience_years' => 9])
            ->assertForbidden()
            ->assertJsonPath('reason', 'application_pending');
    }

    public function test_master_with_a_lapsed_subscription_cannot_edit(): void
    {
        $master = Master::factory()->expired()->create();

        $this->withToken($this->tokenFor($master->client))
            ->patchJson(route('api.v1.master.me.update'), ['experience_years' => 9])
            ->assertForbidden()
            ->assertJsonPath('reason', 'access_expired');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->patchJson(route('api.v1.master.me.update'), ['experience_years' => 9])
            ->assertUnauthorized();
    }
}
