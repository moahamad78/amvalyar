<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Company\KimiaPolyesterDemoProvisioner;
use Illuminate\Console\Command;
use RuntimeException;

final class ProvisionKimiaPolyesterDemo extends Command
{
    protected $signature = 'demo:provision-kimia {--password= : Shared password for demo role users} {--force : Allow execution in production}';

    protected $description = 'Provision the isolated Kimia Polyester demo tenant from KPQ-FI-WI-001';

    public function handle(KimiaPolyesterDemoProvisioner $provisioner): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            throw new RuntimeException('Use --force to provision demo data in production.');
        }

        if (Company::query()->where('code', 'KIMIA-DEMO')->exists()) {
            $this->components->info('Kimia demo company already exists; no data or passwords changed.');

            return self::SUCCESS;
        }

        $password = (string) ($this->option('password') ?: config('demo.kimia.password', ''));
        if (mb_strlen($password) < 12) {
            throw new RuntimeException('Provide --password with at least 12 characters or configure KIMIA_DEMO_PASSWORD.');
        }

        $this->components->info('Provisioning the isolated Kimia Polyester demo tenant...');
        $summary = $provisioner->provision($password);

        $this->table(['Company', 'Roles', 'Users', 'Employees', 'Assets'], [[
            $summary['company_code'], $summary['roles'], $summary['users'], $summary['employees'], $summary['assets'],
        ]]);
        $this->components->info('Kimia Polyester demo is ready. Usernames start with kimia.');

        return self::SUCCESS;
    }
}
