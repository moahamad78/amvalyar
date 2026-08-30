<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseRestoreService;
use Illuminate\Console\Command;
use Throwable;

final class DatabaseRestore extends Command
{
    protected $signature='app:restore-database
        {backup : Backup path on the configured backup disk}
        {--sha256= : Expected SHA256 checksum}
        {--confirm-restore : Required explicit destructive-operation confirmation}';

    protected $description='Restore a verified SQLite database backup outside production.';

    public function handle(DatabaseRestoreService $service): int
    {
        $sha=trim((string)$this->option('sha256'));
        if ($sha === '' || preg_match('/^[a-f0-9]{64}$/i',$sha) !== 1) {
            $this->error('A valid --sha256 checksum is required.');
            return self::FAILURE;
        }

        try {
            $result=$service->restore(
                (string)$this->argument('backup'),
                $sha,
                (bool)$this->option('confirm-restore')
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Database restore completed after checksum and integrity verification.');
        $this->table(['driver','source','bytes','sha256'],[[
            $result['driver'],$result['source'],$result['bytes'],$result['sha256'],
        ]]);

        return self::SUCCESS;
    }
}