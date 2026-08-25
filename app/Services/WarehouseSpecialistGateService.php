<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceStep;
use App\Models\WorkflowStepAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WarehouseSpecialistGateService
{
    public function __construct(
        private readonly InventoryRequestSpecialistRoutingService $routingService,
        private readonly WorkflowApproverResolver $approverResolver
    ) {
    }


    public function finalizeWarehouse(
        WorkflowInstance $instance,
        InventoryRequest $inventoryRequest,
        WorkflowInstanceStep $warehouseStep,
        ?Employee $actorEmployee,
        User $actorUser,
        ?string $comment = null
    ): array {

        return DB::transaction(
            function () use (
                $instance,
                $inventoryRequest,
                $warehouseStep,
                $actorEmployee,
                $actorUser,
                $comment
            ): array {

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $instance->id
                        );


                $inventoryRequest =
                    InventoryRequest::withoutGlobalScopes()
                        ->with('items')
                        ->lockForUpdate()
                        ->findOrFail(
                            $inventoryRequest->id
                        );


                $warehouseStep =
                    WorkflowInstanceStep::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $warehouseStep->id
                        );


                $this->guardWarehouseContext(
                    $instance,
                    $inventoryRequest,
                    $warehouseStep,
                    $actorEmployee,
                    $actorUser
                );


                /*
                |--------------------------------------------------------------------------
                | Exact warehouse selection guard
                |--------------------------------------------------------------------------
                */

                foreach (
                    $inventoryRequest->items
                    as
                    $item
                ) {

                    $approvedQuantity =
                        (float) (
                            $item->approved_quantity
                            ??
                            0
                        );


                    if (
                        abs(
                            $approvedQuantity
                            -
                            round($approvedQuantity)
                        )
                        >
                        0.000001
                    ) {

                        throw ValidationException::withMessages([
                            'allocations.' . $item->id =>
                                'برای اموال، تعداد تأییدشده باید عدد صحیح باشد.',
                        ]);
                    }


                    $needed =
                        (int) round(
                            $approvedQuantity
                        );


                    $selected =
                        InventoryRequestAllocation::query()
                            ->where(
                                'inventory_request_id',
                                $inventoryRequest->id
                            )
                            ->where(
                                'inventory_request_item_id',
                                $item->id
                            )
                            ->whereIn(
                                'status',
                                [
                                    'reserved',
                                    'approved',
                                ]
                            )
                            ->count();


                    if (
                        $selected
                        !==
                        $needed
                    ) {

                        throw ValidationException::withMessages([
                            'allocations.' . $item->id =>
                                'برای «'
                                .
                                $item->item_name
                                .
                                '» باید دقیقاً '
                                .
                                $needed
                                .
                                ' دارایی انتخاب شود. تعداد فعلی: '
                                .
                                $selected
                                .
                                '.',
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Build specialist branches from REAL selected assets
                |--------------------------------------------------------------------------
                */

                $branches =
                    $this->routingService
                        ->buildForWarehouseSelection(
                            $instance,
                            $inventoryRequest,
                            $warehouseStep
                        );


                /*
                |--------------------------------------------------------------------------
                | Lock allocations
                |--------------------------------------------------------------------------
                */

                InventoryRequestAllocation::query()
                    ->where(
                        'inventory_request_id',
                        $inventoryRequest->id
                    )
                    ->where(
                        'status',
                        'reserved'
                    )
                    ->update([
                        'status' =>
                            'approved',

                        'approved_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);


                /*
                |--------------------------------------------------------------------------
                | Finish WAREHOUSE step WITHOUT normal sequential advance
                |--------------------------------------------------------------------------
                */

                $fromStatus =
                    $warehouseStep->status;


                $warehouseStep->update([
                    'status' =>
                        'approved',

                    'acted_at' =>
                        now(),

                    'acted_by_employee_id' =>
                        $actorEmployee?->id,

                    'acted_by_user_id' =>
                        $actorUser->id,

                    'comment' =>
                        $comment,
                ]);


                WorkflowStepAction::query()
                    ->create([
                        'workflow_instance_id' =>
                            $instance->id,

                        'workflow_instance_step_id' =>
                            $warehouseStep->id,

                        'action' =>
                            'approve',

                        'from_status' =>
                            $fromStatus,

                        'to_status' =>
                            'approved',

                        'actor_employee_id' =>
                            $actorEmployee?->id,

                        'actor_user_id' =>
                            $actorUser->id,

                        'comment' =>
                            $comment,

                        'metadata' => [
                            'source' =>
                                'warehouse_specialist_parallel_gate',

                            'parallel_branch_count' =>
                                $branches->count(),
                        ],

                        'acted_at' =>
                            now(),
                    ]);


                /*
                |--------------------------------------------------------------------------
                | Hold normal Workflow while parallel approvals are pending
                |--------------------------------------------------------------------------
                */

                $instance->update([
                    'current_step_id' =>
                        null,

                    'status' =>
                        'pending',
                ]);


                $advancedImmediately =
                    false;


                /*
                 * اگر هیچ مسیر تخصصی برای Assetهای انتخابی تعریف نشده باشد،
                 * مستقیم به مرحله عادی بعد می‌رویم.
                 */
                if (
                    $branches->isEmpty()
                ) {

                    $advancedImmediately =
                        $this->activateNextNormalStep(
                            $instance,
                            $warehouseStep
                        );
                }


                return [

                    'branch_count' =>
                        $branches->count(),

                    'advanced_immediately' =>
                        $advancedImmediately,
                ];
            }
        );
    }


    public function tryAdvanceAfterSpecialists(
        WorkflowInstance $instance,
        WorkflowInstanceStep $warehouseStep
    ): bool {

        return DB::transaction(
            function () use (
                $instance,
                $warehouseStep
            ): bool {

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $instance->id
                        );


                $warehouseStep =
                    WorkflowInstanceStep::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $warehouseStep->id
                        );


                $requiredBranches =
                    WorkflowInstanceBranch::query()
                        ->where(
                            'workflow_instance_id',
                            $instance->id
                        )
                        ->where(
                            'parent_step_id',
                            $warehouseStep->id
                        )
                        ->where(
                            'is_required',
                            true
                        );


                /*
                 * Required rejection = do not advance.
                 */
                if (
                    (clone $requiredBranches)
                        ->where(
                            'status',
                            'rejected'
                        )
                        ->exists()
                ) {

                    return false;
                }


                /*
                 * Any required branch not approved = wait.
                 */
                if (
                    (clone $requiredBranches)
                        ->where(
                            'status',
                            '!=',
                            'approved'
                        )
                        ->exists()
                ) {

                    return false;
                }


                return $this->activateNextNormalStep(
                    $instance,
                    $warehouseStep
                );
            }
        );
    }


    private function activateNextNormalStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $warehouseStep
    ): bool {

        /*
         * Idempotency
         */
        $alreadyPending =
            WorkflowInstanceStep::query()
                ->where(
                    'workflow_instance_id',
                    $instance->id
                )
                ->where(
                    'sort_order',
                    '>',
                    $warehouseStep->sort_order
                )
                ->where(
                    'status',
                    'pending'
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy('id')
                ->first();


        if (
            $alreadyPending
            !==
            null
        ) {

            $instance->update([
                'current_step_id' =>
                    $alreadyPending->id,
            ]);


            return true;
        }


        $next =
            WorkflowInstanceStep::query()
                ->where(
                    'workflow_instance_id',
                    $instance->id
                )
                ->where(
                    'sort_order',
                    '>',
                    $warehouseStep->sort_order
                )
                ->where(
                    'status',
                    'waiting'
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->first();


        if (
            $next
            ===
            null
        ) {

            return false;
        }


        $resolved =
            $this->approverResolver
                ->resolve(
                    $instance,
                    $next
                );


        $dueAt =
            $next->due_hours !== null

                ? now()->addHours(
                    (int) $next->due_hours
                )

                : null;


        $next->update([
            'status' =>
                'pending',

            'resolved_employee_id' =>
                $resolved['employee']?->id,

            'resolved_user_id' =>
                $resolved['user']?->id,

            'activated_at' =>
                now(),

            'due_at' =>
                $dueAt,
        ]);


        $instance->update([
            'current_step_id' =>
                $next->id,

            'status' =>
                'pending',
        ]);


        return true;
    }


    private function guardWarehouseContext(
        WorkflowInstance $instance,
        InventoryRequest $inventoryRequest,
        WorkflowInstanceStep $warehouseStep,
        ?Employee $actorEmployee,
        User $actorUser
    ): void {

        if (
            $warehouseStep->code
            !==
            'WAREHOUSE'
            ||
            $warehouseStep->status
            !==
            'pending'
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله انباردار برای نهایی‌سازی در وضعیت معتبر نیست.',
            ]);
        }


        if (
            (int) $warehouseStep->workflow_instance_id
            !==
            (int) $instance->id
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله انباردار متعلق به این گردش کاری نیست.',
            ]);
        }


        if (
            $instance->subject_type
            !==
            InventoryRequest::class
            ||
            (int) $instance->subject_id
            !==
            (int) $inventoryRequest->id
        ) {

            throw ValidationException::withMessages([
                'request' =>
                    'موضوع گردش کاری با درخواست کالا مطابقت ندارد.',
            ]);
        }


        if (
            (int) $instance->company_id
            !==
            (int) $inventoryRequest->company_id
        ) {

            throw ValidationException::withMessages([
                'request' =>
                    'شرکت درخواست و گردش کاری مطابقت ندارد.',
            ]);
        }


        if (
            !$actorUser->isSuperAdmin()
            &&
            (int) $actorUser->company_id
            !==
            (int) $instance->company_id
        ) {

            throw ValidationException::withMessages([
                'actor' =>
                    'کاربر اقدام‌کننده متعلق به شرکت این گردش کاری نیست.',
            ]);
        }


        $userMatches =
            $warehouseStep->resolved_user_id !== null
            &&
            (int) $warehouseStep->resolved_user_id
            ===
            (int) $actorUser->id;


        $employeeMatches =
            $actorEmployee !== null
            &&
            $warehouseStep->resolved_employee_id !== null
            &&
            (int) $warehouseStep->resolved_employee_id
            ===
            (int) $actorEmployee->id;


        if (
            !$actorUser->isSuperAdmin()
            &&
            !$userMatches
            &&
            !$employeeMatches
        ) {

            throw ValidationException::withMessages([
                'actor' =>
                    'شما مسئول مجاز مرحله انباردار نیستید.',
            ]);
        }
    }
}