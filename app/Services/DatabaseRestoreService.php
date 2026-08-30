<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DatabaseRestoreService
{
    /** @return array{source:string,sha256:string,bytes:int,driver:string} */
    public function restore(string $backupPath, string $expectedSha256, bool $confirmed): array
    {
        if (!$confirmed) {
            throw new RuntimeException('Explicit restore confirmation is required.');
        }

        if (app()->environment('production')) {
            throw new RuntimeException('In-process database restore is forbidden in production.');
        }

        $driver=(string)config('database.default');
        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                'Database restore driver is not configured for '.$driver.'. Production PostgreSQL/MySQL restore requires the native database restore utility.'
            );
        }

        $disk=(string)config('backup.disk','local');
        if (!Storage::disk($disk)->exists($backupPath)) {
            throw new RuntimeException('Backup file does not exist.');
        }

        $stream=Storage::disk($disk)->readStream($backupPath);
        if (!is_resource($stream)) {
            throw new RuntimeException('Backup stream could not be opened.');
        }

        $temp=tempnam(sys_get_temp_dir(),'db-restore-');
        if ($temp === false) {
            fclose($stream);
            throw new RuntimeException('Unable to allocate restore staging file.');
        }

        $out=fopen($temp,'wb');
        if ($out === false) {
            fclose($stream);
            @unlink($temp);
            throw new RuntimeException('Unable to open restore staging file.');
        }

        try {
            stream_copy_to_stream($stream,$out);
        } finally {
            fclose($stream);
            fclose($out);
        }

        try {
            $actual=hash_file('sha256',$temp);
            if ($actual === false || !hash_equals(strtolower(trim($expectedSha256)),strtolower($actual))) {
                throw new RuntimeException('Backup checksum verification failed.');
            }

            $this->assertSqliteDatabase($temp);

            $target=(string)config('database.connections.sqlite.database');
            if ($target === '' || !is_file($target)) {
                throw new RuntimeException('Target SQLite database file does not exist.');
            }

            $safety=$target.'.pre-restore-'.date('Ymd-His').'.bak';
            if (!copy($target,$safety)) {
                throw new RuntimeException('Pre-restore safety snapshot failed.');
            }

            DB::purge('sqlite');

            if (!copy($temp,$target)) {
                @copy($safety,$target);
                throw new RuntimeException('Database replacement failed; safety snapshot restoration attempted.');
            }

            return [
                'source'=>$backupPath,
                'sha256'=>$actual,
                'bytes'=>(int)filesize($temp),
                'driver'=>$driver,
            ];
        } finally {
            @unlink($temp);
        }
    }

    private function assertSqliteDatabase(string $path): void
    {
        try {
            $pdo=new \PDO('sqlite:'.$path);
            $result=$pdo->query('PRAGMA integrity_check');
            $status=$result?->fetchColumn();
            $pdo=null;
        } catch (\Throwable $e) {
            throw new RuntimeException('Backup is not a readable SQLite database.',0,$e);
        }

        if ($status !== 'ok') {
            throw new RuntimeException('SQLite integrity check failed.');
        }
    }
}