<?php

namespace Tests\Feature\Api\V1;

use App\Models\Banner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientCatalogBannersTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_banners_are_public(): void
    {
        $this->getJson(route('api.v1.client.banners'))->assertOk();
    }

    public function test_returns_only_active_banners(): void
    {
        $active = Banner::factory()->active()->create();
        Banner::factory()->inactive()->create();

        $this->getJson(route('api.v1.client.banners'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_banners_follow_the_order_set_in_the_admin_panel(): void
    {
        $last = Banner::factory()->active()->create(['sort_order' => 3]);
        $first = Banner::factory()->active()->create(['sort_order' => 1]);
        $tiedWithFirst = Banner::factory()->active()->create(['sort_order' => 1]);

        $this->getJson(route('api.v1.client.banners'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $tiedWithFirst->id)
            ->assertJsonPath('data.2.id', $last->id);
    }

    public function test_banner_payload_shape(): void
    {
        $banner = Banner::factory()->active()->create([
            'image_path' => 'banners/promo.jpg',
            'url' => 'https://example.com',
            'sort_order' => 2,
        ]);

        $this->getJson(route('api.v1.client.banners'))
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $banner->id,
                'image_url' => Storage::url('banners/promo.jpg'),
                'url' => 'https://example.com',
                'sort_order' => 2,
            ]]]);
    }

    public function test_returns_empty_list_when_there_are_no_active_banners(): void
    {
        Banner::factory()->inactive()->create();

        $this->getJson(route('api.v1.client.banners'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
