<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DatabaseBackupService
{
    /** @return array{disk:string,path:string,sha256:string,bytes:int,driver:string} */
    public function create(): array
    {
        $driver = (string) config('database.default');

        return match ($driver) {
            'sqlite' => $this->createSqliteBackup(),
            'pgsql' => $this->createPostgresBackup(),
            default => throw new RuntimeException(
                'Database backup driver is not configured for '.$driver.'.'
            ),
        };
    }

    /** @return array{disk:string,path:string,sha256:string,bytes:int,driver:string} */
    private function createSqliteBackup(): array
    {
        $driver = 'sqlite';

        $source = (string) config('database.connections.sqlite.database');
        if ($source === '' || ! is_file($source)) {
            throw new RuntimeException('SQLite database file does not exist.');
        }

        // Force SQLite to flush WAL state before copying.
        try {
            DB::connection('sqlite')->statement('PRAGMA wal_checkpoint(FULL)');
        } catch (\Throwable) {
            // Non-WAL databases do not require a checkpoint.
        }

        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open SQLite database for backup.');
        }

        try {
            return $this->storeAndVerify($stream, 'sqlite', $driver, $source);
        } finally {
            fclose($stream);
        }
    }

    /** @return array{disk:string,path:string,sha256:string,bytes:int,driver:string} */
    private function createPostgresBackup(): array
    {
        $temporary = tempnam(sys_get_temp_dir(), 'amvalyar-pg-backup-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to allocate a temporary PostgreSQL backup file.');
        }

        try {
            $settings = $this->postgresSettings();
            $environment = $this->processEnvironment([
                'PGHOST' => $settings['host'],
                'PGPORT' => $settings['port'],
                'PGDATABASE' => $settings['database'],
                'PGUSER' => $settings['username'],
                'PGPASSWORD' => $settings['password'],
                'PGSSLMODE' => $settings['sslmode'],
            ]);
            $command = array_merge(
                (array) config('backup.pg_dump_command', ['pg_dump']),
                ['--format=custom', '--no-owner', '--no-privileges', '--file='.$temporary]
            );

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open($command, $descriptors, $pipes, null, $environment);
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start the PostgreSQL backup utility.');
            }

            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            $error = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0 || ! is_file($temporary) || (int) filesize($temporary) === 0) {
                throw new RuntimeException('PostgreSQL backup utility failed'.($error !== '' ? '.' : ' before producing a backup file.'));
            }

            $stream = fopen($temporary, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Unable to read the PostgreSQL backup file.');
            }

            try {
                return $this->storeAndVerify($stream, 'dump', 'pgsql', $temporary);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * @param  resource  $stream
     * @return array{disk:string,path:string,sha256:string,bytes:int,driver:string}
     */
    private function storeAndVerify($stream, string $extension, string $driver, string $source): array
    {
        $diskName = (string) config('backup.disk', 'local');
        $directory = trim((string) config('backup.path', 'backups/database'), '/');
        $name = 'database-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.'.$extension;
        $path = $directory.'/'.$name;
        $ok = Storage::disk($diskName)->put($path, $stream);

        if ($ok !== true || ! Storage::disk($diskName)->exists($path)) {
            throw new RuntimeException('Backup write verification failed.');
        }

        $backupStream = Storage::disk($diskName)->readStream($path);
        if (! is_resource($backupStream)) {
            throw new RuntimeException('Backup verification stream could not be opened.');
        }

        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $backupStream);
        fclose($backupStream);
        $sha = hash_final($ctx);
        $sourceSha = hash_file('sha256', $source);

        if (! hash_equals($sourceSha, $sha)) {
            Storage::disk($diskName)->delete($path);
            throw new RuntimeException('Backup checksum verification failed.');
        }

        return [
            'disk' => $diskName,
            'path' => $path,
            'sha256' => $sha,
            'bytes' => (int) Storage::disk($diskName)->size($path),
            'driver' => $driver,
        ];
    }

    /** @return array{host:string,port:string,database:string,username:string,password:string,sslmode:string} */
    private function postgresSettings(): array
    {
        $connection = (array) config('database.connections.pgsql', []);
        $url = (string) ($connection['url'] ?? '');
        $parts = $url !== '' ? parse_url($url) : false;
        if ($url !== '' && $parts === false) {
            throw new RuntimeException('PostgreSQL connection URL is invalid.');
        }
        $parts = is_array($parts) ? $parts : [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        $value = static function (string $key, mixed $fallback = '') use ($parts): string {
            return array_key_exists($key, $parts)
                ? rawurldecode((string) $parts[$key])
                : (string) $fallback;
        };

        $database = isset($parts['path'])
            ? ltrim(rawurldecode((string) $parts['path']), '/')
            : (string) ($connection['database'] ?? '');
        $settings = [
            'host' => $value('host', $connection['host'] ?? ''),
            'port' => $value('port', $connection['port'] ?? '5432'),
            'database' => $database,
            'username' => $value('user', $connection['username'] ?? ''),
            'password' => $value('pass', $connection['password'] ?? ''),
            'sslmode' => (string) ($query['sslmode'] ?? $connection['sslmode'] ?? 'prefer'),
        ];
        if (collect($settings)->except('password')->contains(static fn (string $item): bool => trim($item) === '')) {
            throw new RuntimeException('PostgreSQL backup connection settings are incomplete.');
        }

        return $settings;
    }

    /** @param array<string,string> $overrides @return array<string,string> */
    private function processEnvironment(array $overrides): array
    {
        $environment = [];
        foreach ((array) getenv() as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $environment[$key] = (string) $value;
            }
        }

        return array_merge($environment,$overrides);
    }
}
