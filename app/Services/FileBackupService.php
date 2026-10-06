<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class FileBackupService
{
    /** @return array{disk:string,path:string,manifest:string,sha256:string,files:int,bytes:int} */
    public function create(): array
    {
        $diskName = (string) config('backup.disk', 'local');
        $directory = trim((string) config('backup.files_path', 'backups/files'), '/');
        $backupPath = $directory.'/files-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $disk = Storage::disk($diskName);
        $entries = [];
        $bytes = 0;

        foreach ((array) config('backup.file_sources', []) as $label => $source) {
            $source = rtrim((string) $source, DIRECTORY_SEPARATOR);
            if ($source === '' || ! is_dir($source)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                if ($label === 'private' && str_starts_with($relative, 'backups/')) {
                    continue;
                }

                $destination = $backupPath.'/'.trim((string) $label, '/').'/'.$relative;
                $stream = fopen($file->getPathname(), 'rb');
                if ($stream === false) {
                    throw new RuntimeException('Unable to read a file selected for backup.');
                }

                try {
                    if ($disk->put($destination, $stream) !== true) {
                        throw new RuntimeException('File backup write failed.');
                    }
                } finally {
                    fclose($stream);
                }

                $sourceSha = hash_file('sha256', $file->getPathname());
                $backupSha = $this->hashStoredFile($diskName, $destination);
                if (! hash_equals($sourceSha, $backupSha)) {
                    $disk->deleteDirectory($backupPath);
                    throw new RuntimeException('File backup checksum verification failed.');
                }

                $size = (int) $file->getSize();
                $bytes += $size;
                $entries[] = [
                    'source' => (string) $label,
                    'path' => $relative,
                    'bytes' => $size,
                    'sha256' => $sourceSha,
                ];
            }
        }

        usort($entries, static fn (array $left, array $right): int => [$left['source'], $left['path']] <=> [$right['source'], $right['path']]);
        $manifest = $backupPath.'/manifest.json';
        $contents = json_encode([
            'created_at' => now()->toIso8601String(),
            'files' => $entries,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($disk->put($manifest, $contents) !== true || ! $disk->exists($manifest)) {
            $disk->deleteDirectory($backupPath);
            throw new RuntimeException('File backup manifest verification failed.');
        }

        return [
            'disk' => $diskName,
            'path' => $backupPath,
            'manifest' => $manifest,
            'sha256' => hash('sha256', $contents),
            'files' => count($entries),
            'bytes' => $bytes,
        ];
    }

    private function hashStoredFile(string $diskName, string $path): string
    {
        $stream = Storage::disk($diskName)->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('File backup verification stream could not be opened.');
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
