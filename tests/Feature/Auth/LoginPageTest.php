<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function localeProvider(): array
    {
        return [
            'ru' => ['ru'],
            'tk' => ['tk'],
        ];
    }

    public function test_login_page_renders_with_the_props_the_form_needs(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('canResetPassword', true)
                ->has('status')
            );
    }

    /**
     * The brand panel copy must never be hardcoded in the Vue component.
     *
     * @dataProvider localeProvider
     */
    public function test_brand_panel_copy_is_translated(string $locale): void
    {
        foreach (['greeting', 'tagline', 'tagline_accent'] as $line) {
            $key = "auth.login.brand.{$line}";

            $this->assertNotSame($key, __($key, [], $locale), "Missing {$key} for locale {$locale}");
        }
    }

    /**
     * @dataProvider localeProvider
     */
    public function test_login_form_copy_is_translated(string $locale): void
    {
        foreach (['title', 'subtitle', 'email', 'password', 'remember', 'forgot_password', 'submit', 'processing'] as $field) {
            $key = "auth.login.{$field}";

            $this->assertNotSame($key, __($key, [], $locale), "Missing {$key} for locale {$locale}");
        }
    }

    /**
     * The shared PasswordInput toggle announces its state via these keys.
     *
     * @dataProvider localeProvider
     */
    public function test_password_toggle_labels_are_translated(string $locale): void
    {
        foreach (['show', 'hide'] as $action) {
            $key = "layout.password.{$action}";

            $this->assertNotSame($key, __($key, [], $locale), "Missing {$key} for locale {$locale}");
        }
    }

    public function test_brand_icon_asset_is_available(): void
    {
        $this->assertFileExists(public_path('icons/logo/handyman-icon.png'));
    }
}
