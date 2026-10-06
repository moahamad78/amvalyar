<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Services\FileBackupRetentionService;
use App\Services\FileBackupService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class FileBackupServiceTest extends TestCase
{
    public function test_files_are_copied_with_a_verified_manifest_and_backup_tree_is_excluded(): void
    {
        Storage::fake('file-backup-test');
        $source = storage_path('framework/testing/file-backup-source-'.bin2hex(random_bytes(4)));
        mkdir($source.'/nested', 0777, true);
        file_put_contents($source.'/nested/document.txt', 'verified-content');
        file_put_contents($source.'/ignored.txt', 'second-content');

        try {
            config([
                'backup.disk' => 'file-backup-test',
                'backup.files_path' => 'backups/files',
                'backup.file_sources' => ['documents' => $source],
            ]);

            $result = app(FileBackupService::class)->create();

            self::assertSame(2, $result['files']);
            self::assertSame(strlen('verified-content') + strlen('second-content'), $result['bytes']);
            Storage::disk('file-backup-test')->assertExists($result['manifest']);
            Storage::disk('file-backup-test')->assertExists($result['path'].'/documents/nested/document.txt');
            $manifest = json_decode(Storage::disk('file-backup-test')->get($result['manifest']), true, 512, JSON_THROW_ON_ERROR);
            self::assertCount(2, $manifest['files']);
            self::assertSame(hash('sha256', 'verified-content'), collect($manifest['files'])->firstWhere('path', 'nested/document.txt')['sha256']);
        } finally {
            @unlink($source.'/nested/document.txt');
            @unlink($source.'/ignored.txt');
            @rmdir($source.'/nested');
            @rmdir($source);
        }
    }

    public function test_file_backup_retention_deletes_only_expired_backup_directories(): void
    {
        Storage::fake('file-backup-retention');
        config([
            'backup.disk' => 'file-backup-retention',
            'backup.files_path' => 'backups/files',
            'backup.retention_days' => 30,
        ]);

        Carbon::setTestNow('2026-10-06 12:00:00');
        Storage::disk('file-backup-retention')->put('backups/files/files-20260101-010101-abcdef12/manifest.json', '{}');
        Storage::disk('file-backup-retention')->put('backups/files/files-20261006-010101-abcdef12/manifest.json', '{}');
        touch(Storage::disk('file-backup-retention')->path('backups/files/files-20260101-010101-abcdef12/manifest.json'), now()->subDays(40)->getTimestamp());

        $result = app(FileBackupRetentionService::class)->prune();

        self::assertSame(2, $result['scanned']);
        self::assertSame(1, $result['deleted']);
        Storage::disk('file-backup-retention')->assertMissing('backups/files/files-20260101-010101-abcdef12/manifest.json');
        Storage::disk('file-backup-retention')->assertExists('backups/files/files-20261006-010101-abcdef12/manifest.json');
        Carbon::setTestNow();
    }
}
