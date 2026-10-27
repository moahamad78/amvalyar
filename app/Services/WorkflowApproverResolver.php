<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\AssetMovementRequest;
use App\Models\Asset;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Models\WarehouseSiteAccess;
use Illuminate\Validation\ValidationException;

final class WorkflowApproverResolver
{
    /**
     * Resolve the concrete Employee/User responsible
     * for a runtime workflow step.
     *
     * @return array{
     *     employee: ?Employee,
     *     user: ?User
     * }
     */
    public function resolve(
        WorkflowInstance $instance,
        WorkflowInstanceStep $step
    ): array {

        $instance->loadMissing([
            'requesterEmployee',
            'requesterUser',
        ]);

        // Warehouse steps are resolved from the source site of the request,
        // not from a hard-coded person in the workflow template.
        if (in_array($step->code, ['WAREHOUSE', 'FINAL-WAREHOUSE-DELIVERY'], true) || $step->approver_type === 'warehouse_manager') {
            $siteId = $this->warehouseSiteId($instance);
            if ($siteId !== null) return $this->resolveSiteWarehouse($instance, $siteId, $this->warehouseAbility($instance));
        }


        return match (
            $step->approver_type
        ) {

            'direct_manager' =>
                $this->resolveDirectManager(
                    $instance
                ),

            'employee' =>
                $this->resolveExplicitEmployee(
                    $instance,
                    $step
                ),

            'role' =>
                $this->resolveRole(
                    $instance,
                    $step
                ),

            'requester' =>
                $this->resolveRequester(
                    $instance
                ),

            'system' => [
                'employee' => null,
                'user' => null,
            ],

            'asset_manager',
            'warehouse_manager',
            'department_manager' =>
                $this->resolveConfiguredReference(
                    $instance,
                    $step
                ),

            null => [
                'employee' => null,
                'user' => null,
            ],

            default =>
                throw ValidationException::withMessages([
                    'approver_type' =>
                        'نوع تأییدکننده این مرحله توسط موتور گردش کار پشتیبانی نمی‌شود.',
                ]),
        };
    }

    private function warehouseSiteId(WorkflowInstance $instance): ?int
    {
        if ($instance->subject_id === null) return null;
        if ($instance->subject_type === InventoryRequest::class) {
            return InventoryRequest::withoutGlobalScopes()->where('company_id', $instance->company_id)->whereKey($instance->subject_id)->value('site_id');
        }
        if ($instance->subject_type === AssetMovementRequest::class) {
            $assetId = AssetMovementRequest::withoutGlobalScopes()->where('company_id', $instance->company_id)->whereKey($instance->subject_id)->value('asset_id');
            return $assetId ? Asset::withoutGlobalScopes()->where('company_id', $instance->company_id)->whereKey($assetId)->value('current_site_id') : null;
        }
        return null;
    }

    private function warehouseAbility(WorkflowInstance $instance): string
    {
        if ($instance->subject_type === AssetMovementRequest::class && $instance->subject_id !== null) {
            $type = AssetMovementRequest::withoutGlobalScopes()->whereKey($instance->subject_id)->value('movement_type');
            if ($type === AssetMovementRequest::TYPE_RETURN) return 'receive';
        }
        return 'issue';
    }

    private function resolveSiteWarehouse(WorkflowInstance $instance, int $siteId, string $ability): array
    {
        $access = WarehouseSiteAccess::query()->where('company_id', $instance->company_id)->where('site_id', $siteId)
            ->where('can_'.$ability, true)->whereHas('user', fn ($query) => $query->where('is_active', true)
                ->whereHas('role', fn ($role) => $role->whereIn('name', ['warehouse_manager', 'warehouse_supervisor'])->where('is_active', true)))
            ->with('user')->orderBy('id')->first();
        if ($access === null) {
            throw ValidationException::withMessages(['warehouse_site_id' => 'برای انبار سایت این فرایند، انباردارِ دارای دسترسی عملیاتی تعریف نشده است.']);
        }
        $employee = Employee::withoutGlobalScopes()->where('company_id', $instance->company_id)->where('user_id', $access->user_id)->where('is_active', true)->first();
        return ['employee' => $employee, 'user' => $access->user];
    }


    /**
     * Direct manager of requester.
     */
    private function resolveDirectManager(
        WorkflowInstance $instance
    ): array {

        $requester =
            $instance->requesterEmployee;


        if ($requester === null) {

            throw ValidationException::withMessages([
                'requester_employee_id' =>
                    'برای تعیین مدیر مستقیم، پرسنل درخواست‌کننده مشخص نشده است.',
            ]);
        }


        if (
            $requester->manager_employee_id
            ===
            null
        ) {

            throw ValidationException::withMessages([
                'manager_employee_id' =>
                    'برای پرسنل درخواست‌کننده مدیر مستقیم تعریف نشده است.',
            ]);
        }


        $manager =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $instance->company_id
                )
                ->whereKey(
                    $requester->manager_employee_id
                )
                ->first();


        if ($manager === null) {

            throw ValidationException::withMessages([
                'manager_employee_id' =>
                    'مدیر مستقیم درخواست‌کننده در شرکت جاری معتبر نیست.',
            ]);
        }


        return [
            'employee' =>
                $manager,

            'user' =>
                $this->findLinkedUser(
                    $manager
                ),
        ];
    }


    /**
     * Explicit employee selected by workflow designer.
     */
    private function resolveExplicitEmployee(
        WorkflowInstance $instance,
        WorkflowInstanceStep $step
    ): array {

        if (
            $step->approver_reference_id
            ===
            null
        ) {

            throw ValidationException::withMessages([
                'approver_reference_id' =>
                    'برای این مرحله پرسنل تأییدکننده انتخاب نشده است.',
            ]);
        }


        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $instance->company_id
                )
                ->whereKey(
                    $step->approver_reference_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();


        if ($employee === null) {

            throw ValidationException::withMessages([
                'approver_reference_id' =>
                    'پرسنل تأییدکننده معتبر یا فعال نیست.',
            ]);
        }


        return [
            'employee' =>
                $employee,

            'user' =>
                $this->findLinkedUser(
                    $employee
                ),
        ];
    }


    /**
     * Role selected by workflow designer.
     *
     * در این نسخه اگر چند User دارای Role باشند،
     * اولین User فعال همان شرکت انتخاب می‌شود.
     *
     * بعداً این قسمت را به Queue/Group Approval
     * توسعه می‌دهیم.
     */
    private function resolveRole(
        WorkflowInstance $instance,
        WorkflowInstanceStep $step
    ): array {

        if (
            $step->approver_reference_id
            ===
            null
        ) {

            throw ValidationException::withMessages([
                'approver_reference_id' =>
                    'برای این مرحله نقش تأییدکننده انتخاب نشده است.',
            ]);
        }


        $role =
            Role::query()
                ->find(
                    $step->approver_reference_id
                );


        if ($role === null) {

            throw ValidationException::withMessages([
                'approver_reference_id' =>
                    'نقش تأییدکننده معتبر نیست.',
            ]);
        }


        $user =
            User::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $instance->company_id
                )
                ->where(
                    'role_id',
                    $role->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('id')
                ->first();


        if ($user === null) {

            throw ValidationException::withMessages([
                'approver_reference_id' =>
                    'هیچ کاربر فعال دارای این نقش در شرکت وجود ندارد.',
            ]);
        }


        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $instance->company_id
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();


        return [
            'employee' =>
                $employee,

            'user' =>
                $user,
        ];
    }


    /**
     * Requester approves/acts on own step.
     */
    private function resolveRequester(
        WorkflowInstance $instance
    ): array {

        return [
            'employee' =>
                $instance->requesterEmployee,

            'user' =>
                $instance->requesterUser
                ??
                (
                    $instance->requesterEmployee
                        ? $this->findLinkedUser(
                            $instance->requesterEmployee
                        )
                        : null
                ),
        ];
    }


    /**
     * asset_manager / warehouse_manager /
     * department_manager
     *
     * فعلاً Reference می‌تواند به یک Employee
     * مشخص اشاره کند.
     *
     * Smart Selector مرحله بعد این مقدار را
     * بدون وارد کردن ID دستی تنظیم خواهد کرد.
     */
    private function resolveConfiguredReference(
        WorkflowInstance $instance,
        WorkflowInstanceStep $step
    ): array {

        return $this->resolveExplicitEmployee(
            $instance,
            $step
        );
    }


    private function findLinkedUser(
        Employee $employee
    ): ?User {

        if (
            $employee->user_id
            ===
            null
        ) {
            return null;
        }


        return User::withoutGlobalScopes()
            ->where(
                'company_id',
                $employee->company_id
            )
            ->whereKey(
                $employee->user_id
            )
            ->where(
                'is_active',
                true
            )
            ->first();
    }
}
