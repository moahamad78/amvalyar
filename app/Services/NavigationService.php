<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceStep;
use Illuminate\Support\Facades\Route;

final class NavigationService
{
    public function forUser(
        User $user
    ): array {

        $employee =
            $this->resolveEmployee(
                $user
            );

        $counts =
            $this->workspaceCounts(
                $user,
                $employee
            );

        $groups = [];
        $taskCenterCount =
            app(
                \App\Services\TaskCenterService::class
            )->countFor(
                $user
            );

        $groups[] = [
            'key' =>
                'my_tasks',

            'label' =>
                'کارهای من',

            'items' => [
                [
                    'label' =>
                        'مشاهده کارهای منتظر اقدام',

                    'route' =>
                        'task-center.index',

                    'active' =>
                        'task-center.*',

                    'badge' =>
                        $taskCenterCount,
                ],
            ],
        ];



        $main = [];


        $this->push(
            $main,
            $user,
            'درخواست‌های کالا',
            'inventory-requests.index',
            'inventory-requests.*',
            'inventory_requests.view'
        );

                $this->push(
            $main,
            $user,
            'انبارگردانی فیزیکی',
            'stocktakes.index',
            'stocktakes.*',
            'stocktakes.view'
        );
$this->push(
            $main,
            $user,
            'درخواست جابه‌جایی اموال',
            'asset-movement-requests.index',
            'asset-movement-requests.*',
            'asset_movement_requests.view'
        );

        if (!empty($main)) {
            $groups[] = [
                'label' => 'درخواست‌ها و عملیات',
                'items' => $main,
            ];
        }


        $workspaces = [];

        if (
            $user->hasPermission(
                'approvals.view'
            )
        ) {

            $this->push(
                $workspaces,
                $user,
                'کارتابل تأییدها',
                'approvals.index',
                'approvals.*',
                'approvals.view',
                $counts['generic']
            );


            if (
                $counts['specialist']
                >
                0
            ) {

                $this->push(
                    $workspaces,
                    $user,
                    'بررسی تخصصی',
                    'specialist-approvals.index',
                    'specialist-approvals.*',
                    'approvals.view',
                    $counts['specialist']
                );
            }


            if (
                $counts['warehouse_recovery']
                >
                0
            ) {

                $this->push(
                    $workspaces,
                    $user,
                    'اصلاح تخصیص انبار',
                    'warehouse-recoveries.index',
                    'warehouse-recoveries.*',
                    'approvals.view',
                    $counts['warehouse_recovery']
                );
            }


            if (
                $user->hasRole(
                    'warehouse_manager'
                )
                ||
                $counts['final_delivery']
                >
                0
            ) {

                $this->push(
                    $workspaces,
                    $user,
                    'تحویل نهایی انبار',
                    'final-warehouse-deliveries.index',
                    'final-warehouse-deliveries.*',
                    'approvals.view',
                    $counts['final_delivery']
                );
            }
        }


        if (
            $user->hasPermission(
                'asset_completeness.view'
            )
            &&
            (
                $user->hasRole(
                    'asset_manager'
                )
                ||
                $counts['asset_manager']
                >
                0
            )
        ) {

            $this->push(
                $workspaces,
                $user,
                'کارتابل جمع‌دار اموال',
                'asset-manager-requests.index',
                'asset-manager-requests.*',
                'asset_completeness.view',
                $counts['asset_manager']
            );
        }


        if (!empty($workspaces)) {
            $groups[] = [
                'label' => 'کارتابل من',
                'items' => $workspaces,
            ];
        }


        $assets = [];

        $this->push(
            $assets,
            $user,
            'اموال',
            'assets.index',
            'assets.*',
            'assets.view'
        );

        $this->push(
            $assets,
            $user,
            'اسکن پلاک با موبایل',
            'asset-scanner.index',
            'asset-scanner.*',
            'assets.view'
        );

        $this->push(
            $assets,
            $user,
            'اموال سازمانی',
            'organizational-assets.index',
            'organizational-assets.*',
            'assets.view'
        );
        $this->push(
            $assets,
            $user,
            'تعمیر و نگهداری اموال',
            'asset-repairs.index',
            'asset-repairs.*',
            'asset_repairs.view'
        );

        $this->push(
            $assets,
            $user,
            'گردش اموال',
            'asset-transactions.index',
            'asset-transactions.*',
            'assets.view'
        );

        $this->push(
            $assets,
            $user,
            'نواقص شناسنامه',
            'asset-completeness.index',
            'asset-completeness.*',
            'asset_completeness.view'
        );

        $this->push(
            $assets,
            $user,
            'دسته‌بندی و انواع دارایی',
            'asset-reference.index',
            'asset-reference.*',
            'assets.view'
        );

        $this->push(
            $assets,
            $user,
            'تنظیمات کد اموال',
            'asset-settings.code.index',
            'asset-settings.code*',
            'asset_types.manage'
        );

        $this->push(
            $assets,
            $user,
            'طراحی پلاک اموال',
            'asset-settings.plate-templates.index',
            'asset-settings.plate-templates.*',
            'asset_types.manage'
        );



        if (
            $user->isSuperAdmin()
            ||
            $user->hasPermission('employees.create')
            ||
            $user->hasPermission('assets.create')
        ) {
            $this->push(
                $assets,
                $user,
                'ورود گروهی اطلاعات',
                'bulk-import.index',
                'bulk-import.*'
            );
        }

        if (!empty($assets)) {
            $groups[] = [
                'label' => 'اموال',
                'items' => $assets,
            ];
        }


        $organization = [];

        $this->push(
            $organization,
            $user,
            'پرسنل و اموال تحویلی',
            'employees.index',
            'employees.*',
            'employees.view'
        );

        $this->push(
            $organization,
            $user,
            'سایت‌ها',
            'sites.index',
            'sites.*',
            'sites.view'
        );

        $this->push(
            $organization,
            $user,
            'واحدهای سازمانی',
            'departments.index',
            'departments.*',
            'departments.view'
        );

        $this->push(
            $organization,
            $user,
            'موقعیت‌های مکانی',
            'locations.index',
            'locations.*',
            'locations.view'
        );

        if (!empty($organization)) {
            $groups[] = [
                'label' => 'ساختار سازمانی',
                'items' => $organization,
            ];
        }


        $admin = [];

        if ($user->isSuperAdmin()) {

            $this->push(
                $admin,
                $user,
                'شرکت‌ها',
                'companies.index',
                'companies.*'
            );
        }

        $this->push(
            $admin,
            $user,
            'فضای ذخیره‌سازی شرکت',
            'company-storage-profiles.index',
            'company-storage-profiles.*',
            'company_storage.manage'
        );
        $this->push(
            $admin,
            $user,
            'کاربران',
            'users.index',
            'users.*',
            'users.view'
        );

        $this->push(
            $admin,
            $user,
            'نقش‌ها',
            'roles.index',
            'roles.*',
            'roles.view'
        );

        $this->push(
            $admin,
            $user,
            'گردش‌کارها',
            'workflows.index',
            'workflows.*',
            'workflows.view'
        );

        if (!empty($admin)) {
            $groups[] = [
                'label' => 'مدیریت',
                'items' => $admin,
            ];
        }


        $workspace = [];
        $this->push($workspace, $user, 'مرکز گزارش‌ها', 'reports.index', 'reports.index', 'reports.view');
        $this->push($workspace, $user, 'گزارش تعمیر و نگهداری', 'reports.repairs', 'reports.repairs', 'reports.view');
        $this->push($workspace, $user, 'نمودارهای من', 'workspace.charts', 'workspace.charts*', 'reports.view');
        if ($user->isSuperAdmin() || $user->hasRole('company_admin')) {
            $this->push($workspace, $user, 'سابقه ورود و فعالیت', 'workspace.history', 'workspace.history');
        }
        $this->push($workspace, $user, 'ظاهر شخصی', 'workspace.preferences', 'workspace.preferences');
        $groups[] = ['label'=>'گزارش و تنظیمات شخصی', 'items'=>$workspace];
        return $groups;
    }


    private function push(
        array &$items,
        User $user,
        string $label,
        string $routeName,
        string $activePattern,
        ?string $permission = null,
        ?int $badge = null
    ): void {

        if (
            !Route::has(
                $routeName
            )
        ) {
            return;
        }


        if (
            $permission !== null
            &&
            !$user->hasPermission(
                $permission
            )
        ) {
            return;
        }


        $items[] = [
            'label' =>
                $label,

            'route' =>
                $routeName,

            'active' =>
                $activePattern,

            'badge' =>
                $badge,
        ];
    }


    private function workspaceCounts(
        User $user,
        ?Employee $employee
    ): array {

        if (
            $user->isSuperAdmin()
        ) {

            return [

                'generic' =>
                    WorkflowInstanceStep::query()
                        ->where(
                            'status',
                            'pending'
                        )
                        ->count(),

                'specialist' =>
                    WorkflowInstanceBranch::query()
                        ->where(
                            'status',
                            'pending'
                        )
                        ->count(),

                'asset_manager' =>
                    WorkflowInstanceStep::query()
                        ->where(
                            'code',
                            'ASSET-MANAGER'
                        )
                        ->where(
                            'status',
                            'pending'
                        )
                        ->count(),

                'final_delivery' =>
                    WorkflowInstanceStep::query()
                        ->where(
                            'code',
                            'FINAL-WAREHOUSE-DELIVERY'
                        )
                        ->where(
                            'status',
                            'pending'
                        )
                        ->count(),

                'warehouse_recovery' =>
                    WorkflowInstanceBranch::query()
                        ->where(
                            'status',
                            'rejected'
                        )
                        ->count(),
            ];
        }


        return [

            'generic' =>
                $this->assignedSteps(
                    $user,
                    $employee
                )
                    ->where(
                        'status',
                        'pending'
                    )
                    ->count(),

            'specialist' =>
                $this->assignedBranches(
                    $user,
                    $employee
                )
                    ->where(
                        'status',
                        'pending'
                    )
                    ->count(),

            'asset_manager' =>
                $this->assignedSteps(
                    $user,
                    $employee
                )
                    ->where(
                        'code',
                        'ASSET-MANAGER'
                    )
                    ->where(
                        'status',
                        'pending'
                    )
                    ->count(),

            'final_delivery' =>
                $this->assignedSteps(
                    $user,
                    $employee
                )
                    ->where(
                        'code',
                        'FINAL-WAREHOUSE-DELIVERY'
                    )
                    ->where(
                        'status',
                        'pending'
                    )
                    ->count(),

            'warehouse_recovery' =>
                $this->assignedBranches(
                    $user,
                    $employee
                )
                    ->where(
                        'status',
                        'rejected'
                    )
                    ->count(),
        ];
    }


    private function assignedSteps(
        User $user,
        ?Employee $employee
    ) {

        return WorkflowInstanceStep::query()
            ->where(
                function ($query) use (
                    $user,
                    $employee
                ): void {

                    $query->where(
                        'resolved_user_id',
                        $user->id
                    );

                    if (
                        $employee !== null
                    ) {

                        $query->orWhere(
                            'resolved_employee_id',
                            $employee->id
                        );
                    }
                }
            );
    }


    private function assignedBranches(
        User $user,
        ?Employee $employee
    ) {

        return WorkflowInstanceBranch::query()
            ->where(
                function ($query) use (
                    $user,
                    $employee
                ): void {

                    $query->where(
                        'resolved_user_id',
                        $user->id
                    );

                    if (
                        $employee !== null
                    ) {

                        $query->orWhere(
                            'resolved_employee_id',
                            $employee->id
                        );
                    }
                }
            );
    }


    private function resolveEmployee(
        User $user
    ): ?Employee {

        if (
            $user->company_id
            ===
            null
        ) {
            return null;
        }


        return Employee::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->first();
    }
}
