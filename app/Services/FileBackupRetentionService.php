<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FileBackupRetentionService
{
    /** @return array{scanned:int,deleted:int,kept:int} */
    public function prune(?int $days = null): array
    {
        $days ??= (int) config('backup.retention_days', 30);
        if ($days < 1) {
            throw new RuntimeException('Backup retention must be at least one day.');
        }

        $disk = Storage::disk((string) config('backup.disk', 'local'));
        $directory = trim((string) config('backup.files_path', 'backups/files'), '/');
        $cutoff = now()->subDays($days)->getTimestamp();
        $scanned = $deleted = $kept = 0;

        foreach ($disk->directories($directory) as $path) {
            if (! preg_match('/\/files-\d{8}-\d{6}-[a-f0-9]{8}$/i', $path)) {
                continue;
            }

            $scanned++;
            $manifest = $path.'/manifest.json';
            try {
                $modified = $disk->lastModified($manifest);
            } catch (\Throwable) {
                $kept++;

                continue;
            }

            if ($modified < $cutoff && $disk->deleteDirectory($path)) {
                $deleted++;
            } else {
                $kept++;
            }
        }

        return compact('scanned', 'deleted', 'kept');
    }
}
