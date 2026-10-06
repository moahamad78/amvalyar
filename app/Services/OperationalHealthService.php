<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class OperationalHealthService
{
    /** @return array{ready:bool,checks:array{database:bool,cache:bool,storage:bool}} */
    public function check(): array
    {
        $checks = [
            'database' => $this->databaseAvailable(),
            'cache' => $this->cacheAvailable(),
            'storage' => $this->storageAvailable(),
        ];

        return [
            'ready' => ! in_array(false, $checks, true),
            'checks' => $checks,
        ];
    }

    private function databaseAvailable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function cacheAvailable(): bool
    {
        $key = 'operational-health:'.bin2hex(random_bytes(8));

        try {
            Cache::put($key, 'ok', 60);

            return Cache::get($key) === 'ok';
        } catch (\Throwable) {
            return false;
        } finally {
            try {
                Cache::forget($key);
            } catch (\Throwable) {
                // The failed cache operation is already reflected in the result.
            }
        }
    }

    private function storageAvailable(): bool
    {
        $disk = Storage::disk((string) config('monitoring.storage_disk', 'local'));
        $path = '.health/'.bin2hex(random_bytes(8)).'.txt';

        try {
            return $disk->put($path, 'ok') === true
                && $disk->get($path) === 'ok';
        } catch (\Throwable) {
            return false;
        } finally {
            try {
                $disk->delete($path);
            } catch (\Throwable) {
                // The failed storage operation is already reflected in the result.
            }
        }
    }
}
