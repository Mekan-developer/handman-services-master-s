<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Client;
use App\Models\Oblast;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientTest extends TestCase
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
            'phone' => '+99361111222',
        ];
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_clients_index_requires_authentication(): void
    {
        $this->get(route('clients.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_clients_index(): void
    {
        $this->actingAsAdmin();
        Client::factory()->count(3)->create();

        $this->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Clients/Index')->has('clients'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_client(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $this->post(route('clients.store'), $this->validPayload($city))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'name' => 'Иван Иванов',
            'phone' => '+99361111222',
            'city_id' => $city->id,
            'is_blocked' => false,
        ]);
    }

    public function test_store_requires_unique_phone(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        Client::factory()->create(['phone' => '+99361111222', 'city_id' => $city->id]);

        $this->post(route('clients.store'), $this->validPayload($city))
            ->assertSessionHasErrors('phone');
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsAdmin();

        $this->post(route('clients.store'), [])
            ->assertSessionHasErrors(['city_id', 'name', 'phone']);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_client(): void
    {
        $this->actingAsAdmin();
        $client = Client::factory()->create();
        $newCity = City::factory()->create();

        $this->post(route('clients.update', $client->id), [
            'city_id' => $newCity->id,
            'name' => 'Обновлённое имя',
            'phone' => $client->phone,
        ])->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Обновлённое имя',
            'city_id' => $newCity->id,
        ]);
    }

    public function test_update_allows_same_phone_for_same_client(): void
    {
        $this->actingAsAdmin();
        $client = Client::factory()->create(['phone' => '+99361111222']);

        $this->post(route('clients.update', $client->id), [
            'city_id' => $client->city_id,
            'name' => $client->name,
            'phone' => '+99361111222',
        ])->assertRedirect(route('clients.index'));
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_client(): void
    {
        $this->actingAsAdmin();
        $client = Client::factory()->create();

        $this->delete(route('clients.destroy', $client->id))
            ->assertRedirect(route('clients.index'));

        $this->assertModelMissing($client);
    }

    // ── Photo ─────────────────────────────────────────────────────────────────

    public function test_admin_can_attach_a_photo_when_creating_a_client(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $payload = array_merge($this->validPayload($city), [
            'photo' => UploadedFile::fake()->image('ava.jpg', 900, 1200),
        ]);

        $this->post(route('clients.store'), $payload)->assertRedirect(route('clients.index'));

        $client = Client::where('phone', '+99361111222')->firstOrFail();

        $this->assertStringEndsWith('.webp', $client->photo);
        Storage::disk('public')->assertExists($client->photo);

        $info = getimagesizefromstring(Storage::disk('public')->get($client->photo));
        $this->assertSame(512, $info[0]);
        $this->assertSame('image/webp', $info['mime']);
    }

    public function test_admin_photo_upload_rejects_a_non_image(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $city = City::factory()->create();

        $payload = array_merge($this->validPayload($city), [
            'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

        $this->post(route('clients.store'), $payload)->assertSessionHasErrors('photo');
    }

    public function test_updating_a_client_replaces_the_photo_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $client = Client::factory()->create(['photo' => 'clients/old.webp']);
        Storage::disk('public')->put('clients/old.webp', 'stale');

        $this->post(route('clients.update', $client->id), [
            'city_id' => $client->city_id,
            'name' => $client->name,
            'phone' => $client->phone,
            'photo' => UploadedFile::fake()->image('new.jpg', 900, 1200),
        ])->assertRedirect(route('clients.index'));

        $client->refresh();

        $this->assertNotSame('clients/old.webp', $client->photo);
        Storage::disk('public')->assertMissing('clients/old.webp');
        Storage::disk('public')->assertExists($client->photo);
    }

    public function test_updating_a_client_without_a_photo_keeps_the_existing_one(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $client = Client::factory()->create(['photo' => 'clients/keep.webp']);
        Storage::disk('public')->put('clients/keep.webp', 'kept');

        $this->post(route('clients.update', $client->id), [
            'city_id' => $client->city_id,
            'name' => 'Новое имя',
            'phone' => $client->phone,
        ])->assertRedirect(route('clients.index'));

        $this->assertSame('clients/keep.webp', $client->refresh()->photo);
        Storage::disk('public')->assertExists('clients/keep.webp');
    }

    // ── Toggle Block ──────────────────────────────────────────────────────────

    public function test_admin_can_block_client(): void
    {
        $this->actingAsAdmin();
        $client = Client::factory()->create(['is_blocked' => false]);

        $this->post(route('clients.toggle-block', $client->id))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'is_blocked' => true]);
    }

    public function test_admin_can_unblock_client(): void
    {
        $this->actingAsAdmin();
        $client = Client::factory()->blocked()->create();

        $this->post(route('clients.toggle-block', $client->id))
            ->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'is_blocked' => false]);
    }

    // ── Mobile API catalog ────────────────────────────────────────────────────

    public function test_oblasts_catalog_returns_active_only(): void
    {
        Oblast::factory()->create(['is_active' => true, 'name' => 'Ahal']);
        Oblast::factory()->create(['is_active' => false, 'name' => 'Balkan']);

        $this->getJson('/api/v1/client/oblasts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ahal');
    }

    public function test_regions_catalog_returns_active_only(): void
    {
        $oblast = Oblast::factory()->create(['is_active' => true]);
        Region::factory()->create(['is_active' => true, 'oblast_id' => $oblast->id, 'name' => 'Baharly']);
        Region::factory()->create(['is_active' => false, 'oblast_id' => $oblast->id, 'name' => 'Kaka']);

        $this->getJson('/api/v1/client/regions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Baharly');
    }
}
