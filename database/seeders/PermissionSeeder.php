<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\PermissionRegistryService;
use Illuminate\Database\Seeder;

final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(
            PermissionRegistryService::class
        )->sync();
    }
}