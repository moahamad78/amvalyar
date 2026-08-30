<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Services\DeploymentReadinessService;
use Illuminate\Console\Command;
final class DeploymentReadinessCheck extends Command
{
    protected $signature='app:deployment-readiness';
    protected $description='Check deployment prerequisites without exposing secrets.';
    public function handle(DeploymentReadinessService $service): int
    {
        foreach($service->checks() as $name=>$ok){$this->line(($ok?'[OK] ':'[FAIL] ').$name);}
        if($service->failures()!==[]){$this->error('Deployment readiness failed.');return self::FAILURE;}
        $this->info('Deployment readiness passed.');
        return self::SUCCESS;
    }
}