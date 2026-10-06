<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OperationalHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OperationalHealthCheck extends Command
{
    protected $signature = 'app:operational-health-check';

    protected $description = 'Verify database, cache and storage health and optionally send an external heartbeat.';

    public function handle(OperationalHealthService $health): int
    {
        if (! config('monitoring.enabled')) {
            $this->warn('Operational monitoring is disabled.');

            return self::SUCCESS;
        }

        $result = $health->check();
        if (! $result['ready']) {
            Log::critical('Operational health check failed.', ['checks' => $result['checks']]);
            $this->error('Operational health check failed: '.json_encode($result['checks'], JSON_THROW_ON_ERROR));

            return self::FAILURE;
        }

        $heartbeatUrl = trim((string) config('monitoring.heartbeat_url'));
        if ($heartbeatUrl !== '') {
            try {
                Http::timeout(5)->retry(1, 250)->get($heartbeatUrl)->throw();
            } catch (\Throwable $e) {
                Log::critical('Operational heartbeat delivery failed.', ['exception' => $e]);
                $this->error('Operational heartbeat delivery failed.');

                return self::FAILURE;
            }
        }

        $this->info('Operational health check passed.');

        return self::SUCCESS;
    }
}
