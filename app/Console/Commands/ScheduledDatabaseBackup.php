<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseBackupRetentionService;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

final class ScheduledDatabaseBackup extends Command
{
    protected $signature = 'app:scheduled-database-backup';

    protected $description = 'Run the guarded scheduled database backup and retention workflow.';

    public function handle(
        DatabaseBackupService $backup,
        DatabaseBackupRetentionService $retention
    ): int {
        if (! app()->environment('production')) {
            $this->warn('Scheduled database backup skipped outside production.');

            return self::SUCCESS;
        }

        if (! config('backup.enabled')) {
            $this->warn('Scheduled database backup is disabled until off-platform storage is configured.');

            return self::SUCCESS;
        }

        try {
            $result = $backup->create();
            $pruned = $retention->prune();
        } catch (Throwable $e) {
            $this->error('Scheduled database backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Scheduled database backup completed and verified.');
        $this->line('Backup: '.$result['path']);
        $this->line('SHA256: '.$result['sha256']);
        $this->line('Retention deleted: '.$pruned['deleted']);

        return self::SUCCESS;
    }
}
