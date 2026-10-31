<?php

namespace Tests\Unit;

use Tests\TestCase;

class SecurityConfigTest extends TestCase
{
    public function test_cors_configuration_contains_frontend_and_admin_origins(): void
    {
        $allowedOrigins = config('cors.allowed_origins');

        $this->assertContains('http://localhost:3000', $allowedOrigins);
        $this->assertContains('http://localhost:3001', $allowedOrigins);
    }

    public function test_production_cors_does_not_allow_localhost_by_default(): void
    {
        $keys = ['APP_ENV', 'FRONTEND_URL', 'ADMIN_URL', 'CORS_ALLOWED_ORIGINS'];
        $previous = [];

        foreach ($keys as $key) {
            $previous[$key] = [
                'getenv' => getenv($key),
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'had_env' => array_key_exists($key, $_ENV),
                'had_server' => array_key_exists($key, $_SERVER),
            ];
        }

        try {
            foreach ([
                'APP_ENV' => 'production',
                'FRONTEND_URL' => 'https://mbokamedia.com',
                'ADMIN_URL' => 'https://admin.mbokamedia.com',
                'CORS_ALLOWED_ORIGINS' => '',
            ] as $key => $value) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }

            $productionCors = require base_path('config/cors.php');

            $this->assertContains('https://mbokamedia.com', $productionCors['allowed_origins']);
            $this->assertContains('https://admin.mbokamedia.com', $productionCors['allowed_origins']);
            $this->assertNotContains('http://localhost:3000', $productionCors['allowed_origins']);
            $this->assertNotContains('http://127.0.0.1:3001', $productionCors['allowed_origins']);
        } finally {
            foreach ($previous as $key => $values) {
                if ($values['getenv'] === false) {
                    putenv($key);
                } else {
                    putenv("{$key}={$values['getenv']}");
                }

                if ($values['had_env']) {
                    $_ENV[$key] = $values['env'];
                } else {
                    unset($_ENV[$key]);
                }

                if ($values['had_server']) {
                    $_SERVER[$key] = $values['server'];
                } else {
                    unset($_SERVER[$key]);
                }
            }
        }
    }

    public function test_production_sanctum_stateful_domains_do_not_include_loopback_by_default(): void
    {
        $keys = ['APP_ENV', 'APP_URL', 'FRONTEND_URL', 'ADMIN_URL', 'SANCTUM_STATEFUL_DOMAINS'];
        $previous = [];
        $previousAppUrl = config('app.url');

        foreach ($keys as $key) {
            $previous[$key] = [
                'getenv' => getenv($key),
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'had_env' => array_key_exists($key, $_ENV),
                'had_server' => array_key_exists($key, $_SERVER),
            ];
        }

        try {
            foreach ([
                'APP_ENV' => 'production',
                'APP_URL' => 'https://api.mbokamedia.com',
                'FRONTEND_URL' => 'https://mbokamedia.com',
                'ADMIN_URL' => 'https://admin.mbokamedia.com',
            ] as $key => $value) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
            putenv('SANCTUM_STATEFUL_DOMAINS');
            unset($_ENV['SANCTUM_STATEFUL_DOMAINS'], $_SERVER['SANCTUM_STATEFUL_DOMAINS']);
            config()->set('app.url', 'https://api.mbokamedia.com');

            $productionSanctum = require base_path('config/sanctum.php');

            $this->assertContains('mbokamedia.com', $productionSanctum['stateful']);
            $this->assertContains('admin.mbokamedia.com', $productionSanctum['stateful']);
            $this->assertContains('api.mbokamedia.com', $productionSanctum['stateful']);
            $this->assertNotContains('localhost', $productionSanctum['stateful']);
            $this->assertNotContains('127.0.0.1:8000', $productionSanctum['stateful']);
        } finally {
            config()->set('app.url', $previousAppUrl);

            foreach ($previous as $key => $values) {
                if ($values['getenv'] === false) {
                    putenv($key);
                } else {
                    putenv("{$key}={$values['getenv']}");
                }

                if ($values['had_env']) {
                    $_ENV[$key] = $values['env'];
                } else {
                    unset($_ENV[$key]);
                }

                if ($values['had_server']) {
                    $_SERVER[$key] = $values['server'];
                } else {
                    unset($_SERVER[$key]);
                }
            }
        }
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
