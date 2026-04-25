<?php

namespace Tests\Unit;

use Tests\TestCase;

class SecurityConfigTest extends TestCase
{
    public function test_cors_configuration_contains_frontend_and_admin_origins(): void
    {
        $allowedOrigins = config('cors.allowed_origins');

        $this->assertContains(env('FRONTEND_URL', 'http://localhost:3000'), $allowedOrigins);
        $this->assertContains(env('ADMIN_URL', 'http://localhost:3001'), $allowedOrigins);
    }

    public function test_sanctum_stateful_configuration_contains_frontend_and_admin_hosts(): void
    {
        $statefulDomains = config('sanctum.stateful');
        $frontendHost = parse_url(env('FRONTEND_URL', 'http://localhost:3000'), PHP_URL_HOST);
        $frontendPort = parse_url(env('FRONTEND_URL', 'http://localhost:3000'), PHP_URL_PORT);
        $adminHost = parse_url(env('ADMIN_URL', 'http://localhost:3001'), PHP_URL_HOST);
        $adminPort = parse_url(env('ADMIN_URL', 'http://localhost:3001'), PHP_URL_PORT);

        $this->assertContains($frontendHost, $statefulDomains);
        $this->assertContains($adminHost, $statefulDomains);

        if (is_int($frontendPort)) {
            $this->assertContains(sprintf('%s:%d', $frontendHost, $frontendPort), $statefulDomains);
        }

        if (is_int($adminPort)) {
            $this->assertContains(sprintf('%s:%d', $adminHost, $adminPort), $statefulDomains);
        }
    }

    public function test_admin_audit_and_session_security_defaults_are_defined(): void
    {
        $this->assertTrue(config('admin.audit.enabled'));
        $this->assertIsInt(config('admin.audit.pagination_per_page'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertIsBool((bool) config('session.secure'));
    }
}
