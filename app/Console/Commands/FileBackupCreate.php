<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FileBackupService;
use Illuminate\Console\Command;

final class FileBackupCreate extends Command
{
    protected $signature = 'app:backup-files';

    protected $description = 'Create and checksum-verify a backup of application-managed files.';

    public function handle(FileBackupService $backup): int
    {
        $result = $backup->create();

        $this->info('File backup completed and verified.');
        $this->line('Backup: '.$result['path']);
        $this->line('Files: '.$result['files']);
        $this->line('Bytes: '.$result['bytes']);
        $this->line('Manifest SHA256: '.$result['sha256']);

        return self::SUCCESS;
    }
}
