<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsRadiusValidationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->administrator()->create();
    }

    private function storeRadii(int $initial, int $max): void
    {
        Setting::create(['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => (string) $initial]);
        Setting::create(['key' => Setting::MASTER_SEARCH_MAX_RADIUS_KM, 'value' => (string) $max]);
    }

    public function test_settings_page_exposes_the_radii_with_defaults(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->where('masterSearchInitialRadiusKm', Setting::DEFAULT_SEARCH_INITIAL_RADIUS_KM)
                ->where('masterSearchMaxRadiusKm', Setting::DEFAULT_SEARCH_MAX_RADIUS_KM)
            );
    }

    public function test_administrator_can_update_both_radii(): void
    {
        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'master_search_initial_radius_km' => 25,
                'master_search_max_radius_km' => 100,
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => '25']);
        $this->assertDatabaseHas('settings', ['key' => Setting::MASTER_SEARCH_MAX_RADIUS_KM, 'value' => '100']);
    }

    public function test_initial_radius_greater_than_max_is_rejected(): void
    {
        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'master_search_initial_radius_km' => 90,
                'master_search_max_radius_km' => 80,
            ])
            ->assertSessionHasErrors('master_search_initial_radius_km');

        $this->assertDatabaseMissing('settings', ['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM]);
    }

    /** A partial submit is compared against what is already stored. */
    public function test_partial_update_is_validated_against_the_stored_counterpart(): void
    {
        $this->storeRadii(20, 80);

        $this->actingAs($this->administrator())
            ->put(route('settings.update'), ['master_search_initial_radius_km' => 120])
            ->assertSessionHasErrors('master_search_initial_radius_km');

        $this->assertDatabaseHas('settings', ['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => '20']);
    }

    public function test_updating_only_the_app_rules_leaves_the_radii_untouched(): void
    {
        $this->storeRadii(20, 80);

        $this->actingAs($this->administrator())
            ->put(route('settings.update'), ['master_app_rules' => 'Новые правила'])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['key' => Setting::MASTER_SEARCH_INITIAL_RADIUS_KM, 'value' => '20']);
        $this->assertDatabaseHas('settings', ['key' => Setting::MASTER_SEARCH_MAX_RADIUS_KM, 'value' => '80']);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidRadiusProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-5],
            'fractional' => [12.5],
            'non numeric' => ['двадцать'],
            'null' => [null],
            'above the hard ceiling' => [1001],
        ];
    }

    #[DataProvider('invalidRadiusProvider')]
    public function test_invalid_initial_radius_is_rejected(mixed $value): void
    {
        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'master_search_initial_radius_km' => $value,
                'master_search_max_radius_km' => 80,
            ])
            ->assertSessionHasErrors('master_search_initial_radius_km');
    }

    #[DataProvider('invalidRadiusProvider')]
    public function test_invalid_max_radius_is_rejected(mixed $value): void
    {
        $this->actingAs($this->administrator())
            ->put(route('settings.update'), [
                'master_search_initial_radius_km' => 20,
                'master_search_max_radius_km' => $value,
            ])
            ->assertSessionHasErrors('master_search_max_radius_km');
    }

    public function test_operator_cannot_update_the_radii(): void
    {
        $this->actingAs(User::factory()->operator()->create())
            ->put(route('settings.update'), [
                'master_search_initial_radius_km' => 25,
                'master_search_max_radius_km' => 100,
            ])
            ->assertForbidden();
    }
}
