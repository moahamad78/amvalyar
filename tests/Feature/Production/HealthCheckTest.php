<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_readiness_endpoint_checks_the_database_without_exposing_details(): void
    {
        $response = $this->getJson(route('health.ready'))
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'database' => 'ok',
            ]);

        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }

    public function test_laravel_liveness_endpoint_remains_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
