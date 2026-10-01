<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    public function test_generated_urls_use_https_behind_tls_terminating_proxy(): void
    {
        $response = $this->get('/', ['X-Forwarded-Proto' => 'https']);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://', $response->headers->get('Location'));
    }

    public function test_generated_urls_use_http_without_forwarded_proto(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
        $this->assertStringStartsWith('http://', $response->headers->get('Location'));
    }
}
