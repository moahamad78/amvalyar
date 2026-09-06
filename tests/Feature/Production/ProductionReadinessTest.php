<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Services\ProductionReadinessService;
use Tests\TestCase;

final class ProductionReadinessTest extends TestCase
{
    public function test_service_detects_unsafe_production_configuration(): void
    {
        config([
            'app.debug' => true,
            'app.url' => 'http://example.test',
            'database.default' => 'sqlite',
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'session.secure' => false,
            'session.encrypt' => false,
            'session.http_only' => false,
            'production.require_https' => true,
            'production.require_secure_session_cookie' => true,
        ]);

        $failures = app(ProductionReadinessService::class)->failures();

        $this->assertContains('debug_disabled', $failures);
        $this->assertContains('database_supported', $failures);
        $this->assertContains('queue_async', $failures);
        $this->assertContains('cache_persistent', $failures);
        $this->assertContains('session_persistent', $failures);
        $this->assertContains('https_url', $failures);
        $this->assertContains('secure_session_cookie', $failures);
        $this->assertContains('encrypted_session', $failures);
        $this->assertContains('http_only_session_cookie', $failures);
    }

    public function test_service_accepts_safe_production_configuration(): void
    {
        config([
            'app.debug' => false,
            'app.url' => 'https://assets.example.test',
            'database.default' => 'pgsql',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.secure' => true,
            'session.encrypt' => true,
            'session.http_only' => true,
            'production.require_https' => true,
            'production.require_secure_session_cookie' => true,
        ]);

        $this->assertTrue(app(ProductionReadinessService::class)->ready());
    }
}
