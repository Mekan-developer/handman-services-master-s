<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerForbiddenRedirectAssertion();
    }

    /**
     * A 403 in the admin panel is turned into a redirect with a "no access"
     * notification (see App\Support\ForbiddenRedirect). Without a previous
     * page, actions fall back to the same landing route as page visits.
     */
    private function registerForbiddenRedirectAssertion(): void
    {
        if (TestResponse::hasMacro('assertForbiddenRedirect')) {
            return;
        }

        TestResponse::macro('assertForbiddenRedirect', function (string $routeName = 'dashboard'): TestResponse {
            /** @var TestResponse $this */
            return $this->assertRedirect(route($routeName))
                ->assertSessionHas('notification', [
                    'type' => 'error',
                    'message' => __('notifications.forbidden'),
                ]);
        });
    }
}
