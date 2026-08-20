<?php

namespace Tests\Feature\Api\V1;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_content_when_no_rules_saved(): void
    {
        $response = $this->getJson('/api/v1/client/settings');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['content']])
            ->assertJsonPath('data.content', '');
    }

    public function test_returns_saved_client_rules(): void
    {
        Setting::create(['key' => Setting::CLIENT_APP_RULES, 'value' => '<p>Условия</p>']);

        $response = $this->getJson('/api/v1/client/settings');

        $response->assertOk()
            ->assertJsonPath('data.content', '<p>Условия</p>');
    }

    public function test_endpoint_is_public_no_auth_required(): void
    {
        $response = $this->getJson('/api/v1/client/settings');

        $response->assertOk();
    }

    public function test_master_settings_endpoint_is_gone(): void
    {
        $this->getJson('/api/v1/master/settings')->assertNotFound();
    }
}
