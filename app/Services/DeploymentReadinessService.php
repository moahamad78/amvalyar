<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class DeploymentReadinessService
{
    /** @return array<string,bool> */
    public function checks(): array
    {
        $driver = (string) config('database.default');

        return [
            'production_readiness' => app(ProductionReadinessService::class)->ready(),
            'app_key_present' => trim((string) config('app.key')) !== '',
            'health_route_present' => collect(Route::getRoutes()->getRoutes())
                ->contains(fn ($route): bool => $route->uri() === 'up' && in_array('GET', $route->methods(), true)),
            'backup_retention_valid' => (int) config('backup.retention_days', 0) >= 1,
            'native_backup_tool_available' => $this->nativeToolAvailable($driver),
            'migrations_current' => $this->migrationsCurrent(),
            'runtime_directories_writable' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
            'repair_schema_ready' => Schema::hasColumns('asset_repair_requests', [
                'cancelled_from_status', 'cancellation_reason', 'reopened_at', 'reopen_reason',
            ]) && Schema::hasTable('asset_repair_work_orders'),
            'repair_routes_ready' => collect([
                'asset-repairs.index', 'asset-repairs.start', 'asset-repairs.complete',
                'asset-repairs.cancel', 'asset-repairs.reopen', 'reports.repairs',
            ])->every(fn (string $name): bool => Route::has($name)),
        ];
    }

    /** @return list<string> */
    public function failures(): array
    {
        return array_keys(array_filter($this->checks(), static fn (bool $ok): bool => ! $ok));
    }

    private function nativeToolAvailable(string $driver): bool
    {
        if ($driver === 'pgsql') {
            return $this->commandExists('pg_dump');
        }
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->commandExists('mysqldump');
        }

        return false;
    }

    private function commandExists(string $command): bool
    {
        $path = (string) getenv('PATH');
        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            foreach (PHP_OS_FAMILY === 'Windows' ? [$command.'.exe', $command.'.bat', $command.'.cmd', $command] : [$command] as $name) {
                if ($dir !== '' && is_file(rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function migrationsCurrent(): bool
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return false;
        }

        $files = $migrator->getMigrationFiles(database_path('migrations'));
        $ran = $migrator->getRepository()->getRan();

        return array_diff(array_keys($files), $ran) === [];
    }
}
