<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Company;
use App\Models\AssetType;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Compatibility fixtures for legacy tests; never run against application data. */
final class AuditFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('testing') || config('database.default') !== 'sqlite'
            || !str_starts_with(basename((string) config('database.connections.sqlite.database')), 'audit-')) {
            throw new RuntimeException('Audit fixtures require an isolated audit SQLite database.');
        }
        $this->call([PermissionSeeder::class, AssetCategorySeeder::class]);
        foreach ([1, 2] as $id) {
            Company::query()->forceCreate([
                'id' => $id, 'name' => 'Audit company '.$id, 'code' => 'AUDIT-'.$id,
                'status' => 'active', 'plan' => 'internal',
                'license_end' => now()->addYear(), 'max_users' => 100000, 'max_assets' => 1000000,
            ]);
        }
        $role = Role::withoutGlobalScopes()->create([
            'company_id' => 2, 'name' => 'admin', 'display_name' => 'Audit administrator', 'is_active' => true,
        ]);
        $storageRole = Role::withoutGlobalScopes()->create([
            'company_id' => 1, 'name' => 'storage_auditor', 'display_name' => 'Audit storage manager', 'is_active' => true,
        ]);
        $storagePermission = \App\Models\Permission::query()->where('name', 'company_storage.manage')->firstOrFail();
        $storageRole->permissions()->sync([$storagePermission->id]);
        User::withoutGlobalScopes()->create([
            'company_id' => 1, 'role_id' => $storageRole->id, 'username' => 'storage.audit',
            'name' => 'Audit storage manager', 'email' => 'storage-audit@example.test',
            'password' => bcrypt('Audit-only-not-a-production-password'), 'is_active' => true,
        ]);
        $actor = User::withoutGlobalScopes()->forceCreate([
            'id' => 6, 'company_id' => 2, 'role_id' => $role->id, 'username' => 'testadmin',
            'name' => 'Audit administrator', 'email' => 'audit-admin@example.test',
            'password' => bcrypt('Audit-only-not-a-production-password'), 'is_active' => true,
        ]);
        Employee::withoutGlobalScopes()->create([
            'company_id' => 2, 'user_id' => $actor->id, 'personnel_code' => 'AUDIT-ADMIN',
            'display_name' => 'Audit administrator', 'is_active' => true,
        ]);
        foreach ([1 => 'LAPTOP', 2 => 'MOUSE'] as $id => $code) {
            AssetType::withoutGlobalScopes()->forceCreate([
                'id' => $id, 'company_id' => 2, 'asset_category_id' => 1,
                'code' => $code, 'name' => $code, 'coding_code' => str_pad((string) $id, 2, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]);
        }
        User::withoutGlobalScopes()->create([
            'username' => 'audit.root', 'name' => 'Audit root', 'email' => 'audit-root@example.test',
            'password' => bcrypt('Audit-only-not-a-production-password'), 'is_active' => true, 'is_super_admin' => true,
        ]);
        \App\Models\Asset::withoutGlobalScopes()->create([
            'company_id' => 2, 'asset_category_id' => 1, 'asset_type_id' => 1,
            'inventory_code' => 'AUDIT-WAREHOUSE', 'asset_code' => 'AUDIT-WAREHOUSE-CODE',
            'title' => 'Audit warehouse asset', 'status' => 'warehouse', 'custody_type' => 'warehouse', 'is_active' => true,
        ]);
        $exit = \Illuminate\Support\Facades\Artisan::call('demo:provision-kimia', [
            '--password' => 'Audit-only-not-a-production-password',
        ]);
        if ($exit !== 0) {
            throw new RuntimeException('Cannot provision isolated audit demo: '.\Illuminate\Support\Facades\Artisan::output());
        }
    }
}
