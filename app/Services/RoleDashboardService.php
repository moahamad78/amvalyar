<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;

final class RoleDashboardService
{
    public function __construct(
        private readonly TaskCenterService $taskCenter,
        private readonly OperationalAlertService $operationalAlerts
    ) {
    }

    public function forUser(User $user): array
    {
        $tasks = $this->taskCenter->tasksFor($user);
        $alerts = $this->operationalAlerts->alertsFor($user, $tasks);

        return [
            'role_name' => $user->role?->name,
            'role_label' => $user->role?->display_name ?: $user->role?->name ?: 'کاربر',
            'headline' => $this->headlineFor($user),
            'description' => $this->descriptionFor($user),
            'task_count' => $tasks->count(),
            'overdue_count' => $tasks->where('is_overdue', true)->count(),
            'task_preview' => $tasks->take(5)->values(),
            'actions' => $this->actionsFor($user),

            'alert_summary' => [
                'total' => $alerts->count(),
                'critical' => $alerts->where('severity', 'critical')->count(),
                'warning' => $alerts->where('severity', 'warning')->count(),
                'info' => $alerts->where('severity', 'info')->count(),
            ],

            'alert_preview' => $alerts->take(3)->values(),
        ];
    }

    private function headlineFor(User $user): string
    {
        return match ($user->role?->name) {
            'admin' => 'کنترل و مدیریت عملیات شرکت',
            'warehouse_manager' => 'عملیات انبار و گردش اموال',
            'asset_manager' => 'کنترل و نظارت بر اموال',
            'viewer' => 'مشاهده اطلاعات و وضعیت اموال',
            default => 'پنل کاری شما',
        };
    }

    private function descriptionFor(User $user): string
    {
        return match ($user->role?->name) {
            'admin' => 'وضعیت کلی شرکت، کارهای منتظر اقدام و میانبرهای مدیریتی در اختیار شماست.',
            'warehouse_manager' => 'کارهای انبار، تحویل، گردش و درخواست‌های منتظر اقدام را از این بخش پیگیری کنید.',
            'asset_manager' => 'بررسی و کنترل تخصصی اموال و درخواست‌های مرتبط از این بخش انجام می‌شود.',
            'viewer' => 'خلاصه اطلاعاتی که مجاز به مشاهده آن هستید در این بخش نمایش داده می‌شود.',
            default => 'کارها و میانبرهای متناسب با سطح دسترسی شما نمایش داده می‌شوند.',
        };
    }

    private function actionsFor(User $user): array
    {
        $actions = [];

        $this->push(
            $actions,
            Route::has('task-center.index'),
            'کارهای من',
            'task-center.index',
            'مشاهده کارهای منتظر اقدام'
        );

        $this->push(
            $actions,
            $this->can($user, 'assets.view') && Route::has('assets.index'),
            'اموال',
            'assets.index',
            'فهرست و وضعیت اموال'
        );

        $this->push(
            $actions,
            $this->can($user, 'assets.view') && Route::has('organizational-assets.index'),
            'اموال سازمانی',
            'organizational-assets.index',
            'اموال مستقر در واحدها و محل‌های سازمانی'
        );

        $this->push(
            $actions,
            $this->can($user, 'inventory_requests.create') && Route::has('inventory-requests.create'),
            'درخواست کالا',
            'inventory-requests.create',
            'ثبت درخواست جدید'
        );

        $movementAllowed =
            $this->can($user, 'assets.transfer')
            || $this->can($user, 'assets.return')
            || $this->can($user, 'assets.delete');

        $this->push(
            $actions,
            $movementAllowed && Route::has('asset-movement-requests.create'),
            'جابه‌جایی اموال',
            'asset-movement-requests.create',
            'انتقال، عودت یا اسقاط'
        );

        $this->push(
            $actions,
            $this->can($user, 'reports.view') && Route::has('reports.index'),
            'گزارش‌ها',
            'reports.index',
            'گزارش‌های مدیریتی'
        );

        return $actions;
    }

    private function can(User $user, string $permission): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission($permission);
    }

    private function push(
        array &$actions,
        bool $allowed,
        string $label,
        string $route,
        string $description
    ): void {
        if (!$allowed) {
            return;
        }

        $actions[] = [
            'label' => $label,
            'route' => $route,
            'description' => $description,
        ];
    }
}
