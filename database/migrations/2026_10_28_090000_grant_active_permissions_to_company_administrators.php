<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $permissionIds = Permission::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($permissionIds === []) {
            return;
        }

        Role::withoutGlobalScopes()
            ->where('name', 'company_admin')
            ->each(function (Role $role) use ($permissionIds): void {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            });
    }

    public function down(): void
    {
        // Permissions deliberately remain assigned: rolling this migration
        // back must not silently remove administrator access.
    }
};
