<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DatabaseBackupServiceTest extends TestCase
{
    public function test_sqlite_backup_is_written_and_checksum_verified(): void
    {
        Storage::fake('backup-test');

        $source=tempnam(sys_get_temp_dir(),'backup-sqlite-');
        $pdo=new \PDO('sqlite:'.$source);
        $pdo->exec('CREATE TABLE proof (id INTEGER PRIMARY KEY, value TEXT)');
        $pdo->exec("INSERT INTO proof(value) VALUES ('ok')");
        $pdo=null;

        config([
            'database.default'=>'sqlite',
            'database.connections.sqlite.database'=>$source,
            'backup.disk'=>'backup-test',
            'backup.path'=>'backups/database',
        ]);
        DB::purge('sqlite');

        try {
            $result=app(DatabaseBackupService::class)->create();

            Storage::disk('backup-test')->assertExists($result['path']);
            $this->assertSame(hash_file('sha256',$source),$result['sha256']);
            $this->assertGreaterThan(0,$result['bytes']);
            $this->assertSame('sqlite',$result['driver']);
        } finally {
            DB::purge('sqlite');
            @unlink($source);
        }
    }

    public function test_unsupported_database_driver_fails_closed(): void
    {
        config(['database.default'=>'pgsql']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('native database dump utility');

        app(DatabaseBackupService::class)->create();
    }
}