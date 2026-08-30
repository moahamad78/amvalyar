<?php
declare(strict_types=1);
namespace Tests\Feature\Production;
use App\Services\DatabaseBackupRetentionService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
final class DatabaseBackupRetentionTest extends TestCase
{
    public function test_prune_deletes_only_expired_database_backup_files(): void
    {
        Storage::fake('retention-test');
        config(['backup.disk'=>'retention-test','backup.path'=>'backups/database','backup.retention_days'=>30]);
        $disk=Storage::disk('retention-test');
        $old='backups/database/database-20260101-010101-abcdef12.sqlite';
        $new='backups/database/database-20990101-010101-abcdef12.sqlite';
        $other='backups/database/readme.txt';
        $disk->put($old,'old');$disk->put($new,'new');$disk->put($other,'keep');
        touch($disk->path($old),now()->subDays(40)->getTimestamp());
        touch($disk->path($new),now()->getTimestamp());
        $r=app(DatabaseBackupRetentionService::class)->prune();
        $disk->assertMissing($old);$disk->assertExists($new);$disk->assertExists($other);
        $this->assertSame(['scanned'=>2,'deleted'=>1,'kept'=>1],$r);
    }
    public function test_invalid_retention_fails_closed(): void
    {
        $this->expectException(\RuntimeException::class);
        app(DatabaseBackupRetentionService::class)->prune(0);
    }
}