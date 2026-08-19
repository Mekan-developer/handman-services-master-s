<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Client;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MasterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function validPayload(City $city): array
    {
        return [
            'city_id' => $city->id,
            'is_active' => true,
            'category_ids' => [],
        ];
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_masters_index_requires_authentication(): void
    {
        $this->get(route('masters.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_masters_index(): void
    {
        $this->actingAsAdmin();
        Master::factory()->count(3)->create();

        $this->get(route('masters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Masters/Index')->has('masters'));
    }

    public function test_masters_index_shows_average_rating(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        OrderReview::factory()->create(['master_id' => $master->id, 'rating' => 5]);
        OrderReview::factory()->create(['master_id' => $master->id, 'rating' => 4]);

        $this->get(route('masters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masters/Index')
                ->where('masters.data.0.reviews_avg_rating', 4.5)
                ->where('masters.data.0.reviews_count', 2)
            );
    }

    public function test_masters_index_shows_null_rating_when_no_reviews(): void
    {
        $this->actingAsAdmin();
        Master::factory()->create();

        $this->get(route('masters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masters/Index')
                ->where('masters.data.0.reviews_avg_rating', null)
                ->where('masters.data.0.reviews_count', 0)
            );
    }

    // ── Map ───────────────────────────────────────────────────────────────────

    public function test_masters_map_requires_authentication(): void
    {
        $this->get(route('masters.map'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_masters_map(): void
    {
        $this->actingAsAdmin();

        $this->get(route('masters.map'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Masters/Map')->has('masters')->has('cityIds'));
    }

    public function test_map_includes_masters_with_null_access_expires_at(): void
    {
        $this->actingAsAdmin();
        Master::factory()->create(['access_expires_at' => null, 'is_active' => true]);
        Master::factory()->create(['access_expires_at' => now()->addDays(10), 'is_active' => true]);
        Master::factory()->expired()->create();

        $this->get(route('masters.map'))
            ->assertInertia(fn ($page) => $page->where('masters', fn ($masters) => count($masters) === 2));
    }

    // ── Trajectory ────────────────────────────────────────────────────────────

    public function test_trajectory_returns_json_with_points(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();

        MasterLocation::factory()->create([
            'master_id' => $master->id,
            'latitude' => 37.95,
            'longitude' => 58.38,
            'recorded_at' => now()->subHours(2),
        ]);

        $response = $this->getJson(route('masters.trajectory', $master->id));

        $response->assertOk()
            ->assertJsonStructure(['master', 'points'])
            ->assertJsonPath('master.id', $master->id);
    }

    public function test_trajectory_returns_404_for_unknown_master(): void
    {
        $this->actingAsAdmin();

        $this->getJson(route('masters.trajectory', 999))->assertNotFound();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_user_can_update_a_master(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create(['is_active' => true]);
        $newCity = City::factory()->create();

        $payload = array_merge($this->validPayload($newCity), ['is_active' => false]);

        $this->post(route('masters.update', $master), $payload)
            ->assertRedirect(route('masters.index'));

        $this->assertDatabaseHas('masters', [
            'id' => $master->id,
            'city_id' => $newCity->id,
            'is_active' => false,
        ]);
    }

    /** Name and phone belong to the client account and are never writable here. */
    public function test_updating_master_does_not_change_name_or_phone(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create(['name' => 'Старое имя', 'phone' => '+99362000000']);
        $city = City::factory()->create();

        $payload = array_merge($this->validPayload($city), [
            'name' => 'Новое имя',
            'phone' => '+99362999999',
        ]);

        $this->post(route('masters.update', $master), $payload)->assertRedirect();

        $this->assertDatabaseHas('masters', [
            'id' => $master->id,
            'name' => 'Старое имя',
            'phone' => '+99362000000',
        ]);
    }

    public function test_updating_master_syncs_categories(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $master = Master::factory()->create(['city_id' => $city->id]);
        $categories = Category::factory()->count(3)->create();
        $master->categories()->sync($categories->pluck('id'));

        $newCategories = Category::factory()->count(1)->create();
        $payload = array_merge($this->validPayload($city), [
            'category_ids' => $newCategories->pluck('id')->toArray(),
        ]);

        $this->post(route('masters.update', $master), $payload)->assertRedirect();

        $master->refresh();
        $this->assertCount(1, $master->categories);
        $this->assertEquals($newCategories->first()->id, $master->categories->first()->id);
    }

    // ── Photo ─────────────────────────────────────────────────────────────────
    //
    // A master has no photo of its own: the avatar belongs to the client account
    // the profile hangs off, and is uploaded on the clients screen (ClientTest).

    public function test_master_photo_is_read_from_the_client_account(): void
    {
        $this->actingAsAdmin();

        $client = Client::factory()->create(['photo' => 'clients/ava.webp']);
        Master::factory()->create(['client_id' => $client->id]);

        $this->get(route('masters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masters/Index')
                ->where('masters.data.0.photo', 'clients/ava.webp')
                ->where('masters.data.0.photo_url', asset('storage/clients/ava.webp'))
            );
    }

    public function test_master_photo_is_null_when_the_client_has_no_avatar(): void
    {
        $this->actingAsAdmin();

        $client = Client::factory()->create(['photo' => null]);
        Master::factory()->create(['client_id' => $client->id]);

        $this->get(route('masters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('masters.data.0.photo', null)
                ->where('masters.data.0.photo_url', null)
            );
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_user_can_delete_a_master(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();

        $this->delete(route('masters.destroy', $master))
            ->assertRedirect(route('masters.index'));

        $this->assertModelMissing($master);
    }

    /** Losing the master role must never cost someone their account. */
    public function test_deleting_a_master_keeps_the_client_account(): void
    {
        $this->actingAsAdmin();

        $client = Client::factory()->create();
        $master = Master::factory()->create(['client_id' => $client->id]);

        $this->delete(route('masters.destroy', $master))->assertRedirect();

        $this->assertModelMissing($master);
        $this->assertModelExists($client);
    }

    public function test_master_with_completed_orders_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        $master = Master::factory()->create();
        Order::factory()->create(['master_id' => $master->id, 'status' => OrderStatus::Completed]);

        $this->delete(route('masters.destroy', $master))->assertRedirect();

        $this->assertModelExists($master);
    }

    public function test_master_with_an_order_in_progress_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();

        $master = Master::factory()->create();
        Order::factory()->create(['master_id' => $master->id, 'status' => OrderStatus::InProgress]);

        $this->delete(route('masters.destroy', $master))->assertRedirect();

        $this->assertModelExists($master);
    }

    /** Cancelled work is not history worth keeping — it must not block the delete. */
    public function test_master_with_only_cancelled_orders_can_be_deleted(): void
    {
        $this->actingAsAdmin();

        $master = Master::factory()->create();
        Order::factory()->create(['master_id' => $master->id, 'status' => OrderStatus::Cancelled]);

        $this->delete(route('masters.destroy', $master))->assertRedirect();

        $this->assertModelMissing($master);
    }

    public function test_deleting_nonexistent_master_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->delete(route('masters.destroy', 999))->assertNotFound();
    }
}
