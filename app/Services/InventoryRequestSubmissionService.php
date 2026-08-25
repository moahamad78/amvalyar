<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryRequest;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryRequestSubmissionService
{
    public function __construct(
        private readonly WorkflowRuntimeService $workflowRuntime
    ) {
    }


    public function submit(
        InventoryRequest $inventoryRequest,
        User $user
    ): InventoryRequest {

        return DB::transaction(
            function () use (
                $inventoryRequest,
                $user
            ): InventoryRequest {

                $inventoryRequest =
                    InventoryRequest::withoutGlobalScopes()
                        ->with([
                            'items',
                            'requesterEmployee',
                            'requesterUser',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $inventoryRequest->id
                        );


                /*
                |--------------------------------------------------------------------------
                | Basic State
                |--------------------------------------------------------------------------
                */

                if (
                    $inventoryRequest->status
                    !==
                    'draft'
                ) {

                    throw ValidationException::withMessages([
                        'request' =>
                            'فقط درخواست پیش‌نویس قابل ارسال برای تأیید است.',
                    ]);
                }


                if (
                    $inventoryRequest->workflow_instance_id
                    !==
                    null
                ) {

                    throw ValidationException::withMessages([
                        'request' =>
                            'این درخواست قبلاً وارد گردش کاری شده است.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Tenant / Owner
                |--------------------------------------------------------------------------
                */

                if (
                    !$user->isSuperAdmin()
                    &&
                    (
                        (int) $inventoryRequest->company_id
                        !==
                        (int) $user->company_id
                        ||
                        (int) $inventoryRequest->requester_user_id
                        !==
                        (int) $user->id
                    )
                ) {

                    throw ValidationException::withMessages([
                        'request' =>
                            'شما مجاز به ارسال این درخواست نیستید.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Requester
                |--------------------------------------------------------------------------
                */

                if (
                    $inventoryRequest->requesterEmployee
                    ===
                    null
                ) {

                    throw ValidationException::withMessages([
                        'requester_employee_id' =>
                            'پرسنل درخواست‌کننده برای شروع گردش کاری مشخص نیست.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Items
                |--------------------------------------------------------------------------
                */

                if (
                    $inventoryRequest->items->isEmpty()
                ) {

                    throw ValidationException::withMessages([
                        'items' =>
                            'درخواست بدون قلم کالا قابل ارسال نیست.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Default Workflow
                |--------------------------------------------------------------------------
                */

                $workflow =
                    Workflow::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $inventoryRequest->company_id
                        )
                        ->where(
                            'process_type',
                            'inventory_request'
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->where(
                            'is_default',
                            true
                        )
                        ->orderByDesc('version')
                        ->orderByDesc('id')
                        ->first();


                if (
                    $workflow ===
                    null
                ) {

                    throw ValidationException::withMessages([
                        'workflow' =>
                            'گردش کاری پیش‌فرض فعال برای درخواست کالا در این شرکت تعریف نشده است.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Start Workflow
                |--------------------------------------------------------------------------
                */

                $instance =
                    $this->workflowRuntime->start(
                        workflow:
                            $workflow,

                        requesterEmployee:
                            $inventoryRequest->requesterEmployee,

                        requesterUser:
                            $inventoryRequest->requesterUser,

                        subjectType:
                            InventoryRequest::class,

                        subjectId:
                            $inventoryRequest->id,

                        context: [
                            'request_number' =>
                                $inventoryRequest->request_number,

                            'priority' =>
                                $inventoryRequest->priority,

                            'items_count' =>
                                $inventoryRequest->items->count(),

                            'source' =>
                                'inventory_request_submission',
                        ]
                    );


                /*
                |--------------------------------------------------------------------------
                | Update Request
                |--------------------------------------------------------------------------
                */

                $inventoryRequest->update([
                    'workflow_instance_id' =>
                        $instance->id,

                    'status' =>
                        'in_approval',

                    'submitted_at' =>
                        now(),
                ]);


                return $inventoryRequest->fresh([
                    'items',
                    'workflowInstance',
                ]);
            }
        );
    }
}