<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Services\DatabaseBackupRetentionService;
use Illuminate\Console\Command;
use Throwable;
final class DatabaseBackupPrune extends Command
{
    protected $signature='app:backup-prune {--days= : Override configured retention days}';
    protected $description='Delete expired verified database backup files.';
    public function handle(DatabaseBackupRetentionService $service): int
    {
        $raw=$this->option('days');
        $days=$raw===null?null:filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($raw!==null && $days===false){$this->error('--days must be a positive integer.');return self::FAILURE;}
        try{$r=$service->prune($days===null?null:(int)$days);}catch(Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
        $this->table(['scanned','deleted','kept'],[[$r['scanned'],$r['deleted'],$r['kept']]]);
        return self::SUCCESS;
    }
}