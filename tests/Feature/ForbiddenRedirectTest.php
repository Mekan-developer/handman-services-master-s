<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForbiddenRedirectTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_opening_an_admin_only_page_is_redirected_to_dashboard_with_notification(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->get(route('users.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('notification', [
                'type' => 'error',
                'message' => __('notifications.forbidden'),
            ]);
    }

    public function test_inertia_visit_to_an_admin_only_page_is_redirected_to_dashboard(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->get(route('users.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('notification.type', 'error');
    }

    public function test_manager_landing_on_admin_only_page_after_login_ends_up_on_dashboard(): void
    {
        $manager = User::factory()->manager()->create();

        $this->get(route('users.index'))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => $manager->email, 'password' => 'password'])
            ->assertRedirect(route('users.index'));

        $this->get(route('users.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('notification.message', __('notifications.forbidden'));
    }

    public function test_forbidden_action_redirects_back_to_the_previous_page(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->from(route('banners.index'))
            ->post(route('users.store'), [])
            ->assertRedirect(route('banners.index'))
            ->assertSessionHas('notification.type', 'error');
    }

    public function test_forbidden_action_without_previous_page_falls_back_to_dashboard(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->post(route('users.store'), [])
            ->assertRedirect(route('dashboard'));
    }

    public function test_operator_is_redirected_to_profile_because_dashboard_is_closed_to_them(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $this->get(route('users.index'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('notification.type', 'error');
    }

    public function test_operator_opening_dashboard_is_redirected_to_profile(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $this->get(route('dashboard'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_json_request_to_web_route_keeps_plain_403(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $this->getJson(route('pending-otps.data'))
            ->assertForbidden()
            ->assertSessionMissing('notification');
    }

    public function test_broadcasting_auth_keeps_plain_403(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $this->actingAs(User::factory()->operator()->create());

        $this->post('/broadcasting/auth', ['channel_name' => 'private-admin.pending-otps', 'socket_id' => '123.456'])
            ->assertForbidden();
    }

    public function test_api_forbidden_response_stays_json(): void
    {
        Route::middleware('api')->get('api/v1/_forbidden-probe', fn () => abort(403, 'Probe forbidden'));

        $this->actingAs(User::factory()->administrator()->create());

        $this->get('api/v1/_forbidden-probe')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Probe forbidden']);
    }
}
