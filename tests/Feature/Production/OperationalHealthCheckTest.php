<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Services\OperationalHealthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OperationalHealthCheckTest extends TestCase
{
    public function test_health_service_checks_database_cache_and_storage(): void
    {
        Storage::fake('health-test');
        config([
            'cache.default' => 'array',
            'monitoring.storage_disk' => 'health-test',
        ]);

        $result = app(OperationalHealthService::class)->check();

        self::assertTrue($result['ready']);
        self::assertSame([
            'database' => true,
            'cache' => true,
            'storage' => true,
        ], $result['checks']);
    }

    public function test_enabled_command_sends_external_heartbeat_after_successful_checks(): void
    {
        Storage::fake('health-command-test');
        Http::fake(['https://heartbeat.example.test/*' => Http::response('', 200)]);
        config([
            'cache.default' => 'array',
            'monitoring.enabled' => true,
            'monitoring.storage_disk' => 'health-command-test',
            'monitoring.heartbeat_url' => 'https://heartbeat.example.test/amvalyar',
        ]);

        $this->artisan('app:operational-health-check')
            ->expectsOutput('Operational health check passed.')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_schedule_registers_operational_health_check(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('app:operational-health-check')
            ->assertSuccessful();
    }
}
