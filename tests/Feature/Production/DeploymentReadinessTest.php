<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Services\DeploymentReadinessService;
use Tests\TestCase;

final class DeploymentReadinessTest extends TestCase
{
    public function test_local_configuration_fails_deployment_readiness(): void
    {
        config(['app.key' => 'base64:test', 'database.default' => 'sqlite', 'backup.retention_days' => 30]);
        $checks = app(DeploymentReadinessService::class)->checks();
        $this->assertFalse($checks['native_backup_tool_available']);
    }

    public function test_invalid_retention_is_detected(): void
    {
        config(['backup.retention_days' => 0]);
        $this->assertFalse(app(DeploymentReadinessService::class)->checks()['backup_retention_valid']);
    }

    public function test_repair_release_candidate_components_are_ready(): void
    {
        $checks = app(DeploymentReadinessService::class)->checks();
        $this->assertTrue($checks['repair_schema_ready']);
        $this->assertTrue($checks['repair_routes_ready']);
        $this->assertTrue($checks['health_route_present']);
        $this->assertTrue($checks['migrations_current']);
        $this->assertTrue($checks['runtime_directories_writable']);
    }
}
