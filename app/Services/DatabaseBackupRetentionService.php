<?php
declare(strict_types=1);
namespace App\Services;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
final class DatabaseBackupRetentionService
{
    /** @return array{scanned:int,deleted:int,kept:int} */
    public function prune(?int $days=null): array
    {
        $days ??=(int)config('backup.retention_days',30);
        if($days<1){throw new RuntimeException('Backup retention must be at least one day.');}
        $disk=Storage::disk((string)config('backup.disk','local'));
        $dir=trim((string)config('backup.path','backups/database'),'/');
        $cutoff=now()->subDays($days)->getTimestamp();
        $scanned=$deleted=$kept=0;
        foreach($disk->files($dir) as $path){
            if(!preg_match('/\/database-\d{8}-\d{6}-[a-f0-9]{8}\.(sqlite|sql|dump)$/i',$path)){continue;}
            $scanned++;
            try{$modified=$disk->lastModified($path);}catch(\Throwable){$kept++;continue;}
            if($modified<$cutoff){
                if($disk->delete($path)){ $deleted++; } else { $kept++; }
            }else{$kept++;}
        }
        return compact('scanned','deleted','kept');
    }
}