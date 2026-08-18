<?php

namespace Tests\Feature\Api\V1\Client;

use App\Models\City;
use App\Models\Client;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Client avatar: uploaded on complete-registration and replaceable through the
 * profile endpoint. Every upload is downscaled to 512 px wide and stored as WebP.
 */
class ClientPhotoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->city = City::factory()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Merdan Annagurdow',
            'city_id' => $this->city->id,
        ], $overrides);
    }

    private function completeRegistration(Client $client, array $payload)
    {
        Sanctum::actingAs($client, ['*']);

        return $this->withHeader('Accept', 'application/json')
            ->post(route('api.v1.client.auth.complete-registration'), $payload);
    }

    // ── complete-registration ─────────────────────────────────────────────────

    public function test_complete_registration_stores_photo_as_width_512_webp(): void
    {
        $client = Client::factory()->create(['photo' => null]);

        $response = $this->completeRegistration($client, $this->payload([
            'photo' => UploadedFile::fake()->image('avatar.jpg', 1200, 900),
        ]));

        $response->assertOk()
            ->assertJsonPath('client.name', 'Merdan Annagurdow');

        $photo = $client->refresh()->photo;

        $this->assertNotNull($photo);
        $this->assertStringStartsWith('clients/', $photo);
        $this->assertStringEndsWith('.webp', $photo);
        Storage::disk('public')->assertExists($photo);

        $info = getimagesizefromstring(Storage::disk('public')->get($photo));
        $this->assertSame(512, $info[0]);
        $this->assertSame(384, $info[1]);
        $this->assertSame('image/webp', $info['mime']);

        // Absolute, host included — the mobile app loads it straight from JSON.
        $response->assertJsonPath('client.photo', $photo)
            ->assertJsonPath('client.photo_url', asset("storage/{$photo}"));
    }

    public function test_smaller_photo_is_not_upscaled(): void
    {
        $client = Client::factory()->create(['photo' => null]);

        $this->completeRegistration($client, $this->payload([
            'photo' => UploadedFile::fake()->image('small.png', 200, 200),
        ]))->assertOk();

        $info = getimagesizefromstring(Storage::disk('public')->get($client->refresh()->photo));

        $this->assertSame(200, $info[0]);
        $this->assertSame('image/webp', $info['mime']);
    }

    public function test_complete_registration_without_photo_leaves_it_null(): void
    {
        $client = Client::factory()->create(['photo' => null]);

        $this->completeRegistration($client, $this->payload())
            ->assertOk()
            ->assertJsonPath('client.photo', null)
            ->assertJsonPath('client.photo_url', null);

        $client->refresh();

        $this->assertNull($client->photo);
        $this->assertNull($client->photo_url);
    }

    public function test_complete_registration_replaces_the_photo_and_deletes_the_old_file(): void
    {
        Storage::disk('public')->put('clients/old.webp', 'stale');

        $client = Client::factory()->create(['photo' => 'clients/old.webp']);

        $this->completeRegistration($client, $this->payload([
            'photo' => UploadedFile::fake()->image('new.jpg', 800, 800),
        ]))->assertOk();

        $this->assertNotSame('clients/old.webp', $client->refresh()->photo);
        Storage::disk('public')->assertMissing('clients/old.webp');
        Storage::disk('public')->assertExists($client->photo);
    }

    public function test_complete_registration_rejects_a_non_image(): void
    {
        $client = Client::factory()->create(['photo' => null]);

        $this->completeRegistration($client, $this->payload([
            'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->assertNull($client->refresh()->photo);
    }

    public function test_complete_registration_rejects_a_photo_over_5mb(): void
    {
        $client = Client::factory()->create(['photo' => null]);

        $this->completeRegistration($client, $this->payload([
            'photo' => UploadedFile::fake()->image('huge.jpg', 800, 800)->size(6000),
        ]))->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_complete_registration_requires_a_token(): void
    {
        $this->withHeader('Accept', 'application/json')
            ->post(route('api.v1.client.auth.complete-registration'), $this->payload([
                'photo' => UploadedFile::fake()->image('avatar.jpg'),
            ]))
            ->assertUnauthorized();
    }

    // ── PATCH /client/me (multipart via method spoofing) ──────────────────────

    public function test_client_can_update_the_photo_through_the_profile_endpoint(): void
    {
        Storage::disk('public')->put('clients/old.webp', 'stale');

        $client = Client::factory()->create(['photo' => 'clients/old.webp']);

        Sanctum::actingAs($client, ['*']);

        $this->withHeader('Accept', 'application/json')
            ->post(route('api.v1.client.me.update'), [
                '_method' => 'PATCH',
                'photo' => UploadedFile::fake()->image('new.jpg', 1000, 500),
            ])
            ->assertOk()
            ->assertJsonPath('data.photo', fn ($photo) => str_ends_with($photo, '.webp'));

        $info = getimagesizefromstring(Storage::disk('public')->get($client->refresh()->photo));

        $this->assertSame(512, $info[0]);
        Storage::disk('public')->assertMissing('clients/old.webp');
    }

    public function test_profile_update_without_a_photo_keeps_the_existing_one(): void
    {
        $client = Client::factory()->create(['photo' => 'clients/keep.webp']);

        Sanctum::actingAs($client, ['*']);

        $this->patchJson(route('api.v1.client.me.update'), ['name' => 'Täze at'])
            ->assertOk()
            ->assertJsonPath('data.photo', 'clients/keep.webp');

        $this->assertSame('clients/keep.webp', $client->refresh()->photo);
    }
}
