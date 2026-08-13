<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScribeDocsAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ProtectScribeDocs only guards production, so the environment has to be
     * faked — the test suite itself runs under `testing`.
     */
    private function pretendProduction(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
    }

    public function test_docs_are_open_outside_production(): void
    {
        $this->get('/docs')->assertOk();
    }

    public function test_guest_gets_404_in_production(): void
    {
        $this->pretendProduction();

        $this->get('/docs')->assertNotFound();
    }

    public function test_non_administrator_gets_404_in_production(): void
    {
        $this->pretendProduction();

        $this->actingAs(User::factory()->manager()->create())
            ->get('/docs')
            ->assertNotFound();

        $this->actingAs(User::factory()->operator()->create())
            ->get('/docs')
            ->assertNotFound();
    }

    public function test_administrator_can_view_docs_in_production(): void
    {
        $this->pretendProduction();

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/docs')
            ->assertOk();
    }

    public function test_openapi_and_postman_endpoints_are_guarded_too(): void
    {
        $this->pretendProduction();

        $this->get('/docs.openapi')->assertNotFound();
        $this->get('/docs.postman')->assertNotFound();
    }
}
