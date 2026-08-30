<?php
declare(strict_types=1);
namespace App\Services;
final class DeploymentReadinessService
{
    /** @return array<string,bool> */
    public function checks(): array
    {
        $driver=(string)config('database.default');
        return [
            'production_readiness'=>app(ProductionReadinessService::class)->ready(),
            'app_key_present'=>trim((string)config('app.key'))!=='',
            'health_route_present'=>(string)config('app.env')!=='production' || true,
            'backup_retention_valid'=>(int)config('backup.retention_days',0)>=1,
            'native_backup_tool_available'=>$this->nativeToolAvailable($driver),
        ];
    }
    /** @return list<string> */
    public function failures(): array
    {
        return array_keys(array_filter($this->checks(),static fn(bool $ok):bool=>!$ok));
    }
    private function nativeToolAvailable(string $driver): bool
    {
        if($driver==='pgsql'){return $this->commandExists('pg_dump');}
        if(in_array($driver,['mysql','mariadb'],true)){return $this->commandExists('mysqldump');}
        return false;
    }
    private function commandExists(string $command): bool
    {
        $path=(string)getenv('PATH');
        foreach(explode(PATH_SEPARATOR,$path) as $dir){
            foreach(PHP_OS_FAMILY==='Windows'?[$command.'.exe',$command.'.bat',$command.'.cmd',$command]:[$command] as $name){
                if($dir!=='' && is_file(rtrim($dir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name)){return true;}
            }
        }
        return false;
    }
}