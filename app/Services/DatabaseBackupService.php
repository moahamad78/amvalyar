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
        $driver=(string)config('database.default');

        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                'Database backup driver is not configured for '.$driver.'. Production PostgreSQL/MySQL backup requires the native database dump utility.'
            );
        }

        $source=(string)config('database.connections.sqlite.database');
        if ($source === '' || !is_file($source)) {
            throw new RuntimeException('SQLite database file does not exist.');
        }

        // Force SQLite to flush WAL state before copying.
        try {
            DB::connection('sqlite')->statement('PRAGMA wal_checkpoint(FULL)');
        } catch (\Throwable) {
            // Non-WAL databases do not require a checkpoint.
        }

        $stream=fopen($source,'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open SQLite database for backup.');
        }

        $diskName=(string)config('backup.disk','local');
        $directory=trim((string)config('backup.path','backups/database'),'/');
        $name='database-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sqlite';
        $path=$directory.'/'.$name;

        try {
            $ok=Storage::disk($diskName)->put($path,$stream);
        } finally {
            fclose($stream);
        }

        if ($ok !== true || !Storage::disk($diskName)->exists($path)) {
            throw new RuntimeException('Backup write verification failed.');
        }

        $backupStream=Storage::disk($diskName)->readStream($path);
        if (!is_resource($backupStream)) {
            throw new RuntimeException('Backup verification stream could not be opened.');
        }

        $ctx=hash_init('sha256');
        hash_update_stream($ctx,$backupStream);
        fclose($backupStream);
        $sha=hash_final($ctx);
        $sourceSha=hash_file('sha256',$source);

        if (!hash_equals($sourceSha,$sha)) {
            Storage::disk($diskName)->delete($path);
            throw new RuntimeException('Backup checksum verification failed.');
        }

        return [
            'disk'=>$diskName,
            'path'=>$path,
            'sha256'=>$sha,
            'bytes'=>(int)Storage::disk($diskName)->size($path),
            'driver'=>$driver,
        ];
    }
}