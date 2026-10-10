<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->administrator()->create();
    }

    private function operator(): User
    {
        return User::factory()->operator()->create();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_administrator_can_view_settings_page(): void
    {
        Setting::create(['key' => Setting::CLIENT_APP_RULES, 'value' => 'other rules']);

        $response = $this->actingAs($this->administrator())->get(route('settings.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->where('clientAppRules', 'other rules')
            ->missing('masterAppRules')
        );
    }

    public function test_guest_cannot_view_settings_page(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
    }

    public function test_operator_cannot_view_settings_page(): void
    {
        $this->actingAs($this->operator())
            ->get(route('settings.index'))
            ->assertForbidden();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_administrator_can_update_settings(): void
    {
        Setting::create(['key' => Setting::CLIENT_APP_RULES, 'value' => '']);

        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'client_app_rules' => 'Client rules text',
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertDatabaseHas('settings', ['key' => Setting::CLIENT_APP_RULES, 'value' => 'Client rules text']);
    }

    public function test_settings_can_be_updated_to_empty(): void
    {
        Setting::create(['key' => Setting::CLIENT_APP_RULES, 'value' => 'old value']);

        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'client_app_rules' => null,
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertDatabaseHas('settings', ['key' => Setting::CLIENT_APP_RULES, 'value' => null]);
    }

    /** Master rules are gone — submitting the retired key must not resurrect it. */
    public function test_master_app_rules_are_no_longer_stored(): void
    {
        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'client_app_rules' => 'Client rules text',
                'master_app_rules' => 'Master rules text',
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertDatabaseMissing('settings', ['key' => 'master_app_rules']);
    }

    public function test_administrator_can_set_the_decline_restore_window(): void
    {
        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'order_decline_restore_minutes' => 15,
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertDatabaseHas('settings', ['key' => Setting::ORDER_DECLINE_RESTORE_MINUTES, 'value' => '15']);
    }

    public function test_decline_restore_window_must_be_a_positive_number_of_minutes(): void
    {
        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'order_decline_restore_minutes' => 0,
            ])
            ->assertSessionHasErrors('order_decline_restore_minutes');

        $this->assertDatabaseMissing('settings', ['key' => Setting::ORDER_DECLINE_RESTORE_MINUTES]);
    }

    public function test_operator_cannot_update_settings(): void
    {
        $this->actingAs($this->operator())
            ->put(route('settings.update'), [
                'client_app_rules' => 'text',
            ])
            ->assertForbidden();
    }
}
