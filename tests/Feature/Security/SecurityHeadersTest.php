<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_include_security_headers(): void
    {
        config([
            'security.force_https' => false,
            'security.headers.enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_force_https_redirects_when_enabled(): void
    {
        config(['security.force_https' => true]);

        $response = $this->call('GET', '/api/v1/auth/me', server: [
            'HTTP_HOST' => 'api.example.test',
            'HTTPS' => 'off',
            'SERVER_PORT' => '80',
        ]);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://', (string) $response->headers->get('Location'));
        $this->assertStringContainsString('/api/v1/auth/me', (string) $response->headers->get('Location'));
    }

    public function test_force_https_skips_healthcheck(): void
    {
        config(['security.force_https' => true]);

        $this->get('/up')->assertSuccessful();
    }
}
