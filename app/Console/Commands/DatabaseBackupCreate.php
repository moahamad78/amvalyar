<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

final class DatabaseBackupCreate extends Command
{
    protected $signature='app:backup-database';
    protected $description='Create and checksum-verify a database backup using the configured backup disk.';

    public function handle(DatabaseBackupService $service): int
    {
        try {
            $result=$service->create();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Database backup verified.');
        $this->table(['driver','disk','path','bytes','sha256'],[[
            $result['driver'],$result['disk'],$result['path'],$result['bytes'],$result['sha256'],
        ]]);

        return self::SUCCESS;
    }
}