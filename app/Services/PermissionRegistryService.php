<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class PermissionRegistryService
{
    private const CORE = [
        'users' => [
            'view',
            'create',
            'edit',
            'delete',
        ],

        'roles' => [
            'view',
            'create',
            'edit',
            'delete',
        ],

        'permissions' => [
            'manage',
        ],

        'assets' => [
            'view',
            'create',
            'edit',
            'delete',
            'transfer',
            'delivery',
            'return',
        ],

        'reports' => [
            'view',
            'export',
        ],

        'sites' => [
            'view',
            'create',
            'edit',
            'delete',
        ],

        'departments' => [
            'view',
            'create',
            'edit',
            'delete',
        ],

        'locations' => [
            'view',
            'create',
            'edit',
            'delete',
        ],

        'employees' => [
            'view',
            'create',
            'edit',
            'delete',
        ],

        'workflows' => [
            'view',
            'create',
            'edit',
            'delete',
        ],

        'approvals' => [
            'view',
            'act',
        ],

        'inventory_requests' => [
            'view',
            'create',
            'edit',
            'delete',
            'submit',
        ],

        'asset_types' => [
            'manage',
        ],

        'asset_attributes' => [
            'manage',
        ],

        'asset_completeness' => [
            'view',
        ],

        'company_storage' => [
            'manage',
        ],

        'stocktakes' => [
            'view',
            'create',
            'start',
            'count',
            'finalize',
        ],
        'asset_repairs' => [
            'view',
            'create',
            'manage',
        ],
        'asset_movement_requests' => [
            'view',
            'create',
        ],
    ];


    private const MODULE_LABELS = [
        'users' =>
            'کاربران',

        'roles' =>
            'نقش‌ها',

        'permissions' =>
            'دسترسی‌ها',

        'assets' =>
            'اموال',

        'reports' =>
            'گزارش‌ها',

        'sites' =>
            'سایت‌ها',

        'departments' =>
            'واحدهای سازمانی',

        'locations' =>
            'مکان‌ها',

        'employees' =>
            'پرسنل',

        'workflows' =>
            'گردش کار',

        'approvals' =>
            'تأییدها',

        'inventory_requests' =>
            'درخواست کالا',

        'asset_types' =>
            'انواع دارایی',

        'asset_attributes' =>
            'ویژگی‌های شناسنامه اموال',

        'asset_completeness' =>
            'نواقص شناسنامه اموال',

        'company_storage' => 'فضای ذخیره‌سازی شرکت',

        'companies' =>
            'شرکت‌ها',

        'bulk_import' =>
            'ورود گروهی',

        'asset_repairs' =>
            'تعمیر و نگهداری اموال',

        'asset_movement_requests' =>
            'درخواست جابه‌جایی اموال',

        'asset_manager_requests' =>
            'درخواست‌های مدیر اموال',

        'asset_transactions' =>
            'گردش اموال',

        'asset_reference' =>
            'داده‌های مرجع اموال',

        'asset_settings' =>
            'تنظیمات اموال',

        'organizational_assets' =>
            'اموال سازمانی',

        'final_warehouse_deliveries' =>
            'تحویل نهایی انبار',

        'warehouse_recoveries' =>
            'بازیابی انبار',

        'specialist_approvals' =>
            'تأیید تخصصی',

        'operational_alerts' =>
            'هشدارهای عملیاتی',

        'task_center' =>
            'مرکز کارها',
    ];


    private const ACTION_LABELS = [
        'view' =>
            'مشاهده',

        'create' =>
            'ایجاد',

        'edit' =>
            'ویرایش',

        'delete' =>
            'حذف',

        'manage' =>
            'مدیریت',

        'transfer' =>
            'انتقال',

        'delivery' =>
            'تحویل',

        'return' =>
            'عودت',

        'export' =>
            'خروجی گرفتن',

        'submit' =>
            'ارسال برای تأیید',

        'start' =>
            'شروع',

        'count' =>
            'ثبت شمارش',

        'finalize' =>
            'نهایی‌سازی',
        'act' =>
            'اقدام روی تأیید',
    ];


    private const DISCOVERABLE_ACTIONS = [
        'view',
        'create',
        'edit',
        'delete',
        'manage',
        'transfer',
        'delivery',
        'return',
        'export',
        'submit',
        'act',
    ];


    public function sync(): array
    {
        $names =
            $this->registeredNames();

        $created = [];
        $updated = [];

        foreach ($names as $name) {
            [$module, $action] =
                explode(
                    '.',
                    $name,
                    2
                );

            $displayName =
                $this->permissionLabel(
                    $name
                );

            $description =
                'دسترسی «'
                . $displayName
                . '» در سامانه';

            $existing =
                Permission::query()
                    ->where(
                        'name',
                        $name
                    )
                    ->first();

            if ($existing === null) {
                $permission =
                    Permission::query()
                        ->create([
                            'name' =>
                                $name,

                            'display_name' =>
                                $displayName,

                            'description' =>
                                $description,

                            'is_active' =>
                                true,
                        ]);

                $created[] =
                    (int) $permission->id;

                continue;
            }

            $dirty =
                $existing->display_name
                    !== $displayName
                ||
                $existing->description
                    !== $description
                ||
                $existing->is_active
                    !== true;

            if ($dirty) {
                $existing->update([
                    'display_name' =>
                        $displayName,

                    'description' =>
                        $description,

                    'is_active' =>
                        true,
                ]);

                $updated[] =
                    (int) $existing->id;
            }
        }

        /*
         * Super-admin itself bypasses role checks.
         * The built-in system "admin" role is kept complete too,
         * so ordinary users assigned to this role don't miss new permissions.
         */
        $allPermissionIds =
            Permission::query()
                ->where(
                    'is_active',
                    true
                )
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->all();

        Role::query()
            ->whereNull('company_id')
            ->where(
                'is_system',
                true
            )
            ->where(
                'name',
                'admin'
            )
            ->each(
                function (Role $role) use (
                    $allPermissionIds
                ): void {
                    $role->permissions()
                        ->syncWithoutDetaching(
                            $allPermissionIds
                        );
                }
            );

        return [
            'registered_names' =>
                count($names),

            'created_count' =>
                count($created),

            'updated_count' =>
                count($updated),

            'created_ids' =>
                $created,

            'updated_ids' =>
                $updated,
        ];
    }


    public function registeredNames(): array
    {
        $names = [];

        foreach (
            self::CORE
            as $module => $actions
        ) {
            foreach ($actions as $action) {
                $names[] =
                    $module
                    . '.'
                    . $action;
            }
        }

        /*
         * Automatic discovery is intentionally conservative:
         * only real permission checks / navigation permission arguments
         * are discovered. Route names alone do NOT create permissions,
         * preventing fake controls that aren't enforced by code.
         */
        foreach (
            $this->discoverPermissionNames()
            as $name
        ) {
            $names[] =
                $name;
        }

        $names =
            array_values(
                array_unique(
                    $names
                )
            );

        sort($names);

        return $names;
    }


    public function groups(): Collection
    {
        $this->sync();

        return Permission::query()
            ->where(
                'is_active',
                true
            )
            ->orderBy('name')
            ->get()
            ->groupBy(
                fn (Permission $permission): string =>
                    explode(
                        '.',
                        $permission->name,
                        2
                    )[0]
            );
    }


    public function moduleLabel(
        string $module
    ): string {
        return
            self::MODULE_LABELS[$module]
            ?? str_replace(
                '_',
                ' ',
                $module
            );
    }


    public function permissionLabel(
        string $permissionName
    ): string {
        [$module, $action] =
            array_pad(
                explode(
                    '.',
                    $permissionName,
                    2
                ),
                2,
                ''
            );

        $moduleLabel =
            $this->moduleLabel(
                $module
            );

        $actionLabel =
            self::ACTION_LABELS[$action]
            ?? $action;

        return
            $actionLabel
            . ' '
            . $moduleLabel;
    }


    private function discoverPermissionNames(): array
    {
        $roots = [
            app_path(),
            resource_path('views'),
        ];

        $allowedActions =
            implode(
                '|',
                array_map(
                    'preg_quote',
                    self::DISCOVERABLE_ACTIONS
                )
            );

        $names = [];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $root,
                        FilesystemIterator::SKIP_DOTS
                    )
                );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $extension =
                    strtolower(
                        $file->getExtension()
                    );

                if (
                    !in_array(
                        $extension,
                        ['php'],
                        true
                    )
                ) {
                    continue;
                }

                $content =
                    file_get_contents(
                        $file->getPathname()
                    );

                if ($content === false) {
                    continue;
                }

                /*
                 * Explicit hasPermission('module.action')
                 */
                preg_match_all(
                    '/hasPermission\s*\(\s*[\'"]([a-z][a-z0-9_]*\.(?:'
                    . $allowedActions
                    . '))[\'"]\s*\)/i',
                    $content,
                    $matches
                );

                foreach (
                    $matches[1] ?? []
                    as $name
                ) {
                    $names[] =
                        strtolower(
                            $name
                        );
                }

                /*
                 * Common associative permission metadata:
                 * 'permission' => 'module.action'
                 */
                preg_match_all(
                    '/[\'"]permission[\'"]\s*=>\s*[\'"]([a-z][a-z0-9_]*\.(?:'
                    . $allowedActions
                    . '))[\'"]/i',
                    $content,
                    $matches
                );

                foreach (
                    $matches[1] ?? []
                    as $name
                ) {
                    $names[] =
                        strtolower(
                            $name
                        );
                }

                /*
                 * NavigationService push(...) convention:
                 * final argument is the actual permission.
                 */
                preg_match_all(
                    '/\$this->push\s*\((?:(?!\);)[\s\S]){0,800}?[\'"]([a-z][a-z0-9_]*\.(?:'
                    . $allowedActions
                    . '))[\'"]\s*\);/i',
                    $content,
                    $matches
                );

                foreach (
                    $matches[1] ?? []
                    as $name
                ) {
                    $names[] =
                        strtolower(
                            $name
                        );
                }
            }
        }

        return
            array_values(
                array_unique(
                    $names
                )
            );
    }
}