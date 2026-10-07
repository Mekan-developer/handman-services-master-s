<?php

namespace Tests\Feature\Api\V1\Client;

use App\Enums\DevicePlatform;
use App\Models\Client;
use App\Models\ClientDevice;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * FCM token registration: the app posts its token after sign-in and on every
 * refresh, and deletes it right before logout.
 */
class ClientDeviceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOKEN = 'fcm-token-abc:APA91bExample';

    private function actingAsClient(?Client $client = null): Client
    {
        $client ??= Client::factory()->create();

        Sanctum::actingAs($client, ['*']);

        return $client;
    }

    public function test_client_registers_device_token(): void
    {
        $client = $this->actingAsClient();

        $this->postJson(route('api.v1.client.devices.store'), [
            'token' => self::TOKEN,
            'platform' => 'android',
        ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('client_devices', [
            'client_id' => $client->id,
            'token' => self::TOKEN,
            'platform' => 'android',
        ]);
    }

    public function test_registering_same_token_twice_keeps_one_row(): void
    {
        $client = $this->actingAsClient();

        $this->postJson(route('api.v1.client.devices.store'), ['token' => self::TOKEN, 'platform' => 'android'])->assertCreated();
        $this->postJson(route('api.v1.client.devices.store'), ['token' => self::TOKEN, 'platform' => 'ios'])->assertOk();

        $this->assertSame(1, ClientDevice::count());
        $this->assertSame(DevicePlatform::Ios, $client->devices()->first()->platform);
    }

    public function test_token_moves_to_account_that_signed_in_last(): void
    {
        $previousOwner = Client::factory()->create();
        ClientDevice::factory()->for($previousOwner)->create(['token' => self::TOKEN]);

        $newOwner = $this->actingAsClient();

        $this->postJson(route('api.v1.client.devices.store'), ['token' => self::TOKEN, 'platform' => 'ios'])->assertOk();

        $this->assertSame([], $previousOwner->routeNotificationForFcm());
        $this->assertSame(['ru' => [self::TOKEN]], $newOwner->routeNotificationForFcm());
    }

    public function test_device_remembers_app_language_from_header(): void
    {
        $client = $this->actingAsClient();

        $this->withHeader('X-Locale', 'tk')
            ->postJson(route('api.v1.client.devices.store'), ['token' => self::TOKEN, 'platform' => 'android'])
            ->assertCreated()
            ->assertJsonPath('data.locale', 'tk');

        $this->assertSame(['tk' => [self::TOKEN]], $client->routeNotificationForFcm());
    }

    public function test_register_validates_payload(): void
    {
        $this->actingAsClient();

        $this->postJson(route('api.v1.client.devices.store'), ['platform' => 'windows'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'platform']);
    }

    public function test_guest_cannot_register_device(): void
    {
        $this->postJson(route('api.v1.client.devices.store'), ['token' => self::TOKEN, 'platform' => 'android'])
            ->assertUnauthorized();
    }

    public function test_client_removes_own_device_token(): void
    {
        $client = $this->actingAsClient();
        ClientDevice::factory()->for($client)->create(['token' => self::TOKEN]);

        $this->deleteJson(route('api.v1.client.devices.destroy'), ['token' => self::TOKEN])
            ->assertNoContent();

        $this->assertDatabaseMissing('client_devices', ['token' => self::TOKEN]);
    }

    public function test_client_cannot_remove_foreign_device_token(): void
    {
        ClientDevice::factory()->create(['token' => self::TOKEN]);

        $this->actingAsClient();

        $this->deleteJson(route('api.v1.client.devices.destroy'), ['token' => self::TOKEN])
            ->assertNoContent();

        $this->assertDatabaseHas('client_devices', ['token' => self::TOKEN]);
    }

    public function test_devices_are_deleted_with_client(): void
    {
        $device = ClientDevice::factory()->create();

        $device->client->delete();

        $this->assertModelMissing($device);
    }
}
