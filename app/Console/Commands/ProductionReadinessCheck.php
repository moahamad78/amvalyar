<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ProductionReadinessService;
use Illuminate\Console\Command;

final class ProductionReadinessCheck extends Command
{
    protected $signature='app:production-readiness';
    protected $description='Validate critical production runtime configuration without exposing secrets.';

    public function handle(ProductionReadinessService $service): int
    {
        $checks=$service->checks();
        foreach($checks as $name=>$ok){
            $this->line(sprintf('[%s] %s',$ok ? 'OK' : 'FAIL',$name));
        }

        if(!$service->ready()){
            $this->error('Production readiness check failed.');
            return self::FAILURE;
        }

        $this->info('Production readiness check passed.');
        return self::SUCCESS;
    }
}