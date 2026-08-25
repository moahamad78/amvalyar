<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceStep;
use Illuminate\Support\Collection;

final class TaskCenterService
{
    public function tasksFor(
        User $user
    ): Collection {
        if ($user->company_id === null) {
            return collect();
        }

        $employee =
            $this->employeeFor(
                $user
            );

        return collect()
            ->concat(
                $this->stepTasks(
                    $user,
                    $employee
                )
            )
            ->concat(
                $this->specialistTasks(
                    $user,
                    $employee
                )
            )
            ->concat(
                $this->recoveryTasks(
                    $user,
                    $employee
                )
            )
            ->sort(
                function (
                    array $a,
                    array $b
                ): int {

                    /*
                     * Overdue first.
                     */
                    if (
                        $a['is_overdue']
                        !==
                        $b['is_overdue']
                    ) {
                        return
                            $a['is_overdue']
                                ? -1
                                : 1;
                    }

                    /*
                     * Tasks with due date before tasks
                     * without a due date.
                     */
                    $aDue =
                        $a['due_at'];

                    $bDue =
                        $b['due_at'];

                    if (
                        $aDue !== null
                        &&
                        $bDue === null
                    ) {
                        return -1;
                    }

                    if (
                        $aDue === null
                        &&
                        $bDue !== null
                    ) {
                        return 1;
                    }

                    if (
                        $aDue !== null
                        &&
                        $bDue !== null
                    ) {

                        $dueCompare =
                            $aDue->getTimestamp()
                            <=>
                            $bDue->getTimestamp();

                        if ($dueCompare !== 0) {
                            return $dueCompare;
                        }
                    }

                    $aActivated =
                        $a['activated_at']
                            ?->getTimestamp()
                        ??
                        PHP_INT_MAX;

                    $bActivated =
                        $b['activated_at']
                            ?->getTimestamp()
                        ??
                        PHP_INT_MAX;

                    return
                        $aActivated
                        <=>
                        $bActivated;
                }
            )
            ->values();
    }

    public function countFor(
        User $user
    ): int {
        return
            $this->tasksFor(
                $user
            )->count();
    }

    private function stepTasks(
        User $user,
        ?Employee $employee
    ): Collection {
        return WorkflowInstanceStep::query()
            ->where(
                'status',
                'pending'
            )
            ->whereHas(
                'instance',
                function ($query) use ($user): void {

                    $query
                        ->where(
                            'company_id',
                            $user->company_id
                        )
                        ->where(
                            'status',
                            'pending'
                        );
                }
            )
            ->where(
                function ($query) use (
                    $user,
                    $employee
                ): void {

                    $query->where(
                        'resolved_user_id',
                        $user->id
                    );

                    if ($employee !== null) {

                        $query->orWhere(
                            'resolved_employee_id',
                            $employee->id
                        );
                    }
                }
            )
            ->with([
                'instance',
            ])
            ->orderBy('id')
            ->get()
            ->filter(
                function (
                    WorkflowInstanceStep $step
                ): bool {

                    $instance =
                        $step->instance;

                    /*
                     * Only actionable current step belongs
                     * in Task Center.
                     */
                    return
                        $instance !== null
                        &&
                        (int)
                        $instance->current_step_id
                        ===
                        (int)
                        $step->id;
                }
            )
            ->map(
                fn (
                    WorkflowInstanceStep $step
                ): array =>
                    $this->presentStep(
                        $step
                    )
            );
    }

    private function specialistTasks(
        User $user,
        ?Employee $employee
    ): Collection {
        return WorkflowInstanceBranch::query()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'status',
                'pending'
            )
            ->whereHas(
                'instance',
                function ($query): void {

                    $query->where(
                        'status',
                        'pending'
                    );
                }
            )
            ->where(
                function ($query) use (
                    $user,
                    $employee
                ): void {

                    $query->where(
                        'resolved_user_id',
                        $user->id
                    );

                    if ($employee !== null) {

                        $query->orWhere(
                            'resolved_employee_id',
                            $employee->id
                        );
                    }
                }
            )
            ->with([
                'instance',
                'category',
            ])
            ->orderBy('activated_at')
            ->orderBy('id')
            ->get()
            ->map(
                fn (
                    WorkflowInstanceBranch $branch
                ): array =>
                    $this->presentSpecialistBranch(
                        $branch
                    )
            );
    }

    private function recoveryTasks(
        User $user,
        ?Employee $employee
    ): Collection {
        return WorkflowInstanceBranch::query()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'status',
                'rejected'
            )
            ->whereHas(
                'instance',
                function ($query): void {

                    $query->where(
                        'status',
                        'pending'
                    );
                }
            )

            /*
             * WarehouseRecoveryController authorizes
             * recovery against the warehouse parent step,
             * not merely against the rejected branch.
             */
            ->whereHas(
                'parentStep',
                function ($query) use (
                    $user,
                    $employee
                ): void {

                    $query->where(
                        function ($actor) use (
                            $user,
                            $employee
                        ): void {

                            $actor->where(
                                'resolved_user_id',
                                $user->id
                            );

                            if ($employee !== null) {

                                $actor->orWhere(
                                    'resolved_employee_id',
                                    $employee->id
                                );
                            }
                        }
                    );
                }
            )
            ->with([
                'instance',
                'category',
                'parentStep',
            ])
            ->orderBy('acted_at')
            ->orderBy('id')
            ->get()
            ->map(
                fn (
                    WorkflowInstanceBranch $branch
                ): array =>
                    $this->presentRecoveryBranch(
                        $branch
                    )
            );
    }

    private function presentStep(
        WorkflowInstanceStep $step
    ): array {
        $instance =
            $step->instance;

        $route =
            $this->routeForStep(
                $step
            );

        return [
            'task_key' =>
                'step-' . $step->id,

            'task_type' =>
                'step',

            'task_id' =>
                $step->id,

            'step_id' =>
                $step->id,

            'branch_id' =>
                null,

            'instance_id' =>
                $instance?->id,

            'title' =>
                $step->name
                ?: $this->labelForCode(
                    $step->code
                ),

            'code' =>
                $step->code,

            'process_type' =>
                $instance?->process_type,

            'process_label' =>
                $this->processLabel(
                    $instance?->process_type
                ),

            'subject_id' =>
                $instance?->subject_id,

            'workflow_name' =>
                $instance?->workflow_name,

            'category_name' =>
                null,

            'activated_at' =>
                $step->activated_at,

            'due_at' =>
                $step->due_at,

            'is_overdue' =>
                $step->due_at !== null
                &&
                $step->due_at->isPast(),

            'route' =>
                $route['name'],

            'route_parameter' =>
                $step->id,

            'workspace' =>
                $route['workspace'],

            'status_label' =>
                'منتظر اقدام',
        ];
    }

    private function presentSpecialistBranch(
        WorkflowInstanceBranch $branch
    ): array {
        $instance =
            $branch->instance;

        return [
            'task_key' =>
                'specialist-branch-'
                .
                $branch->id,

            'task_type' =>
                'branch',

            'task_id' =>
                $branch->id,

            'step_id' =>
                null,

            'branch_id' =>
                $branch->id,

            'instance_id' =>
                $instance?->id,

            'title' =>
                'بررسی تخصصی'
                .
                (
                    $branch->category?->name
                        ? ' - '
                            .
                            $branch->category->name
                        : ''
                ),

            'code' =>
                $branch->branch_key
                ?: 'SPECIALIST',

            'process_type' =>
                $instance?->process_type,

            'process_label' =>
                $this->processLabel(
                    $instance?->process_type
                ),

            'subject_id' =>
                $instance?->subject_id,

            'workflow_name' =>
                $instance?->workflow_name,

            'category_name' =>
                $branch->category?->name,

            'activated_at' =>
                $branch->activated_at,

            'due_at' =>
                null,

            'is_overdue' =>
                false,

            'route' =>
                'specialist-approvals.show',

            'route_parameter' =>
                $branch->id,

            'workspace' =>
                'specialist',

            'status_label' =>
                'منتظر بررسی تخصصی',
        ];
    }

    private function presentRecoveryBranch(
        WorkflowInstanceBranch $branch
    ): array {
        $instance =
            $branch->instance;

        return [
            'task_key' =>
                'recovery-branch-'
                .
                $branch->id,

            'task_type' =>
                'branch',

            'task_id' =>
                $branch->id,

            'step_id' =>
                null,

            'branch_id' =>
                $branch->id,

            'instance_id' =>
                $instance?->id,

            'title' =>
                'اصلاح تخصیص انبار'
                .
                (
                    $branch->category?->name
                        ? ' - '
                            .
                            $branch->category->name
                        : ''
                ),

            'code' =>
                $branch->branch_key
                ?: 'WAREHOUSE-RECOVERY',

            'process_type' =>
                $instance?->process_type,

            'process_label' =>
                $this->processLabel(
                    $instance?->process_type
                ),

            'subject_id' =>
                $instance?->subject_id,

            'workflow_name' =>
                $instance?->workflow_name,

            'category_name' =>
                $branch->category?->name,

            /*
             * rejected branch has no due_at.
             * acted_at represents rejection time.
             */
            'activated_at' =>
                $branch->acted_at
                ??
                $branch->activated_at,

            'due_at' =>
                null,

            'is_overdue' =>
                false,

            'route' =>
                'warehouse-recoveries.show',

            'route_parameter' =>
                $branch->id,

            'workspace' =>
                'warehouse_recovery',

            'status_label' =>
                'نیازمند اصلاح',
        ];
    }

    private function routeForStep(
        WorkflowInstanceStep $step
    ): array {
        return match (
            $step->code
        ) {

            'ASSET-MANAGER' => [
                'name' =>
                    'asset-manager-requests.show',

                'workspace' =>
                    'asset_manager',
            ],

            'FINAL-WAREHOUSE-DELIVERY' => [
                'name' =>
                    'final-warehouse-deliveries.show',

                'workspace' =>
                    'final_delivery',
            ],

            default => [
                'name' =>
                    'approvals.show',

                'workspace' =>
                    'approval',
            ],
        };
    }

    private function processLabel(
        ?string $processType
    ): string {
        return match (
            $processType
        ) {

            'inventory_request' =>
                'درخواست کالا',

            'asset_transfer' =>
                'انتقال مال',

            'asset_return' =>
                'عودت مال',

            'asset_disposal' =>
                'اسقاط مال',

            default =>
                $processType
                ?: 'گردش کاری',
        };
    }

    private function labelForCode(
        ?string $code
    ): string {
        return match ($code) {

            'DIRECT-MANAGER' =>
                'تأیید مدیر مستقیم',

            'ASSET-MANAGER' =>
                'بررسی جمعدار اموال',

            'WAREHOUSE' =>
                'تأیید انباردار',

            'FINAL-WAREHOUSE-DELIVERY' =>
                'تحویل نهایی انبار',

            default =>
                $code
                ?: 'کار در انتظار',
        };
    }

    private function employeeFor(
        User $user
    ): ?Employee {
        return Employee::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'is_active',
                true
            )
            ->first();
    }
}