<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BootstrapDemoAdmin extends Command
{
    protected $signature = 'app:bootstrap-demo-admin';

    protected $description = 'Create the first demo super administrator from environment secrets';

    public function handle(): int
    {
        if (User::query()->where('is_super_admin', true)->exists()) {
            $this->components->info('A super administrator already exists; no changes made.');

            return self::SUCCESS;
        }

        $username = trim((string) config('demo.admin.username'));
        $name = trim((string) config('demo.admin.name'));
        $email = trim((string) config('demo.admin.email'));
        $password = (string) config('demo.admin.password');

        if ($username === '' && $email === '' && $password === '') {
            $this->components->warn('Demo administrator secrets are not configured; skipping bootstrap.');

            return self::SUCCESS;
        }

        if ($username === '' || $name === '' || $email === '' || $password === '') {
            throw new RuntimeException('All DEMO_ADMIN_* values must be configured together.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('DEMO_ADMIN_EMAIL must be a valid email address.');
        }

        if (mb_strlen($username) > 100 || mb_strlen($password) < 12) {
            throw new RuntimeException('Demo admin username must be at most 100 characters and password at least 12 characters.');
        }

        DB::transaction(function () use ($username, $name, $email, $password): void {
            User::query()->create([
                'company_id' => null,
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role_id' => null,
                'is_active' => true,
                'is_super_admin' => true,
            ]);
        });

        $this->components->info('Demo super administrator created.');

        return self::SUCCESS;
    }
}
