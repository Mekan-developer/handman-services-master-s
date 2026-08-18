<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Client;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\OrderReview;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'name' => 'Иван Иванов',
            'phone' => '+99362123456',
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

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_user_can_create_a_master(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $this->post(route('masters.store'), $this->validPayload($city))
            ->assertRedirect(route('masters.index'));

        $this->assertDatabaseHas('masters', ['name' => 'Иван Иванов', 'phone' => '+99362123456']);
    }

    public function test_creating_master_syncs_categories(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $categories = Category::factory()->count(2)->create();

        $payload = array_merge($this->validPayload($city), [
            'category_ids' => $categories->pluck('id')->toArray(),
        ]);

        $this->post(route('masters.store'), $payload)->assertRedirect();

        $master = Master::where('phone', '+99362123456')->first();
        $this->assertCount(2, $master->categories);
    }

    public function test_store_fails_when_name_is_missing(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $payload = $this->validPayload($city);
        $payload['name'] = '';

        $this->post(route('masters.store'), $payload)
            ->assertSessionHasErrors('name');
    }

    public function test_store_fails_when_phone_is_duplicate(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        Master::factory()->create(['phone' => '+99362123456']);

        $this->post(route('masters.store'), $this->validPayload($city))
            ->assertSessionHasErrors('phone');
    }

    public function test_store_fails_when_city_does_not_exist(): void
    {
        $this->actingAsAdmin();
        $payload = $this->validPayload(City::factory()->make(['id' => 9999]));
        $payload['city_id'] = 9999;

        $this->post(route('masters.store'), $payload)
            ->assertSessionHasErrors('city_id');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_user_can_update_a_master(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $newCity = City::factory()->create();

        $payload = array_merge($this->validPayload($newCity), ['name' => 'Новое имя']);

        $this->post(route('masters.update', $master), $payload)
            ->assertRedirect(route('masters.index'));

        $this->assertDatabaseHas('masters', ['id' => $master->id, 'name' => 'Новое имя']);
    }

    public function test_update_allows_same_phone_for_same_master(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create(['phone' => '+99362999999']);
        $city = City::factory()->create();

        $payload = array_merge($this->validPayload($city), ['phone' => '+99362999999']);

        $this->post(route('masters.update', $master), $payload)
            ->assertRedirect(route('masters.index'));
    }

    public function test_update_fails_when_phone_belongs_to_another_master(): void
    {
        $this->actingAsAdmin();
        Master::factory()->create(['phone' => '+99362111111']);
        $master = Master::factory()->create(['phone' => '+99362222222']);
        $city = City::factory()->create();

        $payload = array_merge($this->validPayload($city), ['phone' => '+99362111111']);

        $this->post(route('masters.update', $master), $payload)
            ->assertSessionHasErrors('phone');
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
            'phone' => $master->phone,
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

    public function test_master_form_no_longer_accepts_a_photo(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $payload = array_merge($this->validPayload($city), [
            'photo' => UploadedFile::fake()->image('master.jpg', 600, 800),
        ]);

        $this->post(route('masters.store'), $payload)->assertRedirect();

        // Silently ignored rather than stored: the file belongs on the client.
        $this->assertCount(0, Storage::disk('public')->allFiles());
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

    public function test_deleting_nonexistent_master_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->delete(route('masters.destroy', 999))->assertNotFound();
    }
}
