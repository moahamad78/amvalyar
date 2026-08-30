<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Services\DatabaseRestoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DatabaseRestoreServiceTest extends TestCase
{
    public function test_restore_requires_explicit_confirmation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Explicit restore confirmation');

        app(DatabaseRestoreService::class)->restore('x.sqlite',str_repeat('a',64),false);
    }

    public function test_restore_rejects_checksum_mismatch_without_touching_target(): void
    {
        Storage::fake('restore-test');
        [$target,$backup]=$this->sqliteFixtures();
        Storage::disk('restore-test')->put('backups/restore.sqlite',file_get_contents($backup));

        config([
            'database.default'=>'sqlite',
            'database.connections.sqlite.database'=>$target,
            'backup.disk'=>'restore-test',
        ]);
        DB::purge('sqlite');
        $before=hash_file('sha256',$target);

        try {
            app(DatabaseRestoreService::class)->restore('backups/restore.sqlite',str_repeat('0',64),true);
            $this->fail('Checksum mismatch should fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('checksum',$e->getMessage());
            $this->assertSame($before,hash_file('sha256',$target));
        } finally {
            DB::purge('sqlite'); @unlink($target); @unlink($backup);
        }
    }

    public function test_verified_sqlite_backup_can_restore_and_creates_safety_snapshot(): void
    {
        Storage::fake('restore-test');
        [$target,$backup]=$this->sqliteFixtures();
        Storage::disk('restore-test')->put('backups/restore.sqlite',file_get_contents($backup));

        config([
            'database.default'=>'sqlite',
            'database.connections.sqlite.database'=>$target,
            'backup.disk'=>'restore-test',
        ]);
        DB::purge('sqlite');

        try {
            $result=app(DatabaseRestoreService::class)->restore(
                'backups/restore.sqlite',
                hash_file('sha256',$backup),
                true
            );

            $pdo=new \PDO('sqlite:'.$target);
            $value=$pdo->query('SELECT value FROM proof LIMIT 1')->fetchColumn();
            $pdo=null;

            $this->assertSame('backup',$value);
            $this->assertSame(hash_file('sha256',$backup),$result['sha256']);
            $this->assertNotEmpty(glob($target.'.pre-restore-*.bak') ?: []);
        } finally {
            DB::purge('sqlite');
            foreach(glob($target.'.pre-restore-*.bak') ?: [] as $f){@unlink($f);}
            @unlink($target); @unlink($backup);
        }
    }

    /** @return array{0:string,1:string} */
    private function sqliteFixtures(): array
    {
        $target=tempnam(sys_get_temp_dir(),'restore-target-');
        $backup=tempnam(sys_get_temp_dir(),'restore-backup-');

        foreach([[$target,'target'],[$backup,'backup']] as [$path,$value]){
            $pdo=new \PDO('sqlite:'.$path);
            $pdo->exec('CREATE TABLE proof (id INTEGER PRIMARY KEY, value TEXT)');
            $stmt=$pdo->prepare('INSERT INTO proof(value) VALUES (?)');
            $stmt->execute([$value]);
            $pdo=null;
        }

        return [$target,$backup];
    }
}