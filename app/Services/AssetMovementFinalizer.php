<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovementRequest;
use App\Models\AssetTransaction;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetMovementFinalizer
{
    public function __construct(
        private readonly AssetCustodyService $custodyService
    ) {
    }

    public function finalizeCompleted(
        WorkflowInstance $instance,
        ?Employee $actorEmployee = null,
        ?User $actorUser = null
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Ignore non-movement workflows
        |--------------------------------------------------------------------------
        */

        if (
            $instance->subject_type
            !==
            AssetMovementRequest::class
        ) {
            return;
        }


        if (
            $instance->subject_id
            ===
            null
        ) {

            throw ValidationException::withMessages([
                'movement_request' =>
                    'شناسه درخواست عملیات دارایی در گردش کاری موجود نیست.',
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Must really be completed
        |--------------------------------------------------------------------------
        */

        if (
            $instance->status
            !==
            'completed'
            ||
            $instance->current_step_id
            !==
            null
        ) {

            throw ValidationException::withMessages([
                'workflow' =>
                    'گردش کاری عملیات دارایی هنوز تکمیل نشده است.',
            ]);
        }


        DB::transaction(
            function () use (
                $instance,
                $actorEmployee,
                $actorUser
            ): void {

                $movementRequest =
                    AssetMovementRequest::withoutGlobalScopes()
                        ->whereKey(
                            $instance->subject_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | Tenant / instance integrity
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $movementRequest->company_id
                    !==
                    (int) $instance->company_id
                ) {

                    throw ValidationException::withMessages([
                        'movement_request' =>
                            'شرکت درخواست عملیات با گردش کاری مطابقت ندارد.',
                    ]);
                }


                if (
                    $movementRequest->workflow_instance_id
                    ===
                    null
                    ||
                    (int) $movementRequest->workflow_instance_id
                    !==
                    (int) $instance->id
                ) {

                    throw ValidationException::withMessages([
                        'movement_request' =>
                            'گردش کاری متصل به درخواست عملیات معتبر نیست.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Strong idempotency
                |--------------------------------------------------------------------------
                |
                | اگر قبلاً Transaction نهایی این Request ساخته شده باشد،
                | اجرای دوباره هیچ Transaction جدیدی ایجاد نمی‌کند.
                |
                */

                $existingTransaction =
                    AssetTransaction::withoutGlobalScopes()
                        ->where(
                            'asset_movement_request_id',
                            $movementRequest->id
                        )
                        ->lockForUpdate()
                        ->first();


                if (
                    $existingTransaction
                    !==
                    null
                ) {

                    if (
                        $movementRequest->status
                        !==
                        AssetMovementRequest::STATUS_COMPLETED
                    ) {

                        $movementRequest->update([

                            'status' =>
                                AssetMovementRequest::STATUS_COMPLETED,

                            'completed_at' =>
                                $movementRequest->completed_at
                                ?? now(),
                        ]);
                    }


                    return;
                }


                if (
                    !in_array(
                        $movementRequest->status,
                        [
                            AssetMovementRequest::STATUS_SUBMITTED,
                            AssetMovementRequest::STATUS_APPROVED,
                        ],
                        true
                    )
                ) {

                    if (
                        $movementRequest->status
                        ===
                        AssetMovementRequest::STATUS_COMPLETED
                    ) {
                        return;
                    }


                    throw ValidationException::withMessages([
                        'movement_request' =>
                            'درخواست عملیات در وضعیت قابل نهایی‌سازی نیست.',
                    ]);
                }


                $asset =
                    Asset::withoutGlobalScopes()
                        ->whereKey(
                            $movementRequest->asset_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                if (
                    (int) $asset->company_id
                    !==
                    (int) $movementRequest->company_id
                ) {

                    throw ValidationException::withMessages([
                        'asset' =>
                            'شرکت دارایی با درخواست عملیات مطابقت ندارد.',
                    ]);
                }


                $createdByUserId =
                    $actorUser?->id
                    ??
                    $movementRequest->requested_by_user_id;


                /*
                |--------------------------------------------------------------------------
                | Validate actor tenant
                |--------------------------------------------------------------------------
                */

                if (
                    $actorEmployee !== null
                    &&
                    (int) $actorEmployee->company_id
                    !==
                    (int) $movementRequest->company_id
                ) {

                    throw ValidationException::withMessages([
                        'actor' =>
                            'پرسنل نهایی‌کننده متعلق به شرکت درخواست نیست.',
                    ]);
                }


                if (
                    $actorUser !== null
                    &&
                    !$actorUser->isSuperAdmin()
                    &&
                    (int) $actorUser->company_id
                    !==
                    (int) $movementRequest->company_id
                ) {

                    throw ValidationException::withMessages([
                        'actor' =>
                            'کاربر نهایی‌کننده متعلق به شرکت درخواست نیست.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Current holder
                |--------------------------------------------------------------------------
                */

                $currentHolderId =
                    $this->currentHolderId(
                        $asset
                    );

                $beforeCustody = $this->custodyService->snapshot($asset);


                $transactionData = [

                    'company_id' =>
                        $asset->company_id,

                    'asset_id' =>
                        $asset->id,

                    'asset_movement_request_id' =>
                        $movementRequest->id,

                    'from_user_id' =>
                        null,

                    'to_user_id' =>
                        null,

                    'from_custody_type' =>
                        $beforeCustody['custody_type'] ?? null,

                    'to_custody_type' =>
                        null,

                    'from_employee_id' =>
                        $beforeCustody['custody_employee_id'] ?? null,

                    'to_employee_id' =>
                        null,

                    'from_department_id' =>
                        $beforeCustody['custody_department_id'] ?? null,

                    'to_department_id' =>
                        null,

                    'from_site_id' =>
                        $beforeCustody['site_id'] ?? null,

                    'to_site_id' =>
                        null,

                    'from_location_id' =>
                        $beforeCustody['location_id'] ?? null,

                    'to_location_id' =>
                        null,

                    'type' =>
                        null,

                    'plate_number' =>
                        $asset->asset_code,

                    'description' =>
                        $movementRequest->reason
                        ??
                        $movementRequest->notes,

                    'created_by' =>
                        $createdByUserId,
                ];


                /*
                |--------------------------------------------------------------------------
                | TRANSFER
                |--------------------------------------------------------------------------
                */

                if (
                    $movementRequest->movement_type
                    ===
                    AssetMovementRequest::TYPE_TRANSFER
                ) {

                    if (
                        $asset->status
                        !==
                        'assigned'
                    ) {

                        throw ValidationException::withMessages([
                            'asset' =>
                                'دارایی در زمان نهایی‌سازی انتقال، تحویل‌شده نیست.',
                        ]);
                    }


                    if (
                        $currentHolderId
                        ===
                        null
                    ) {

                        throw ValidationException::withMessages([
                            'asset' =>
                                'دارنده فعلی دارایی قابل تشخیص نیست.',
                        ]);
                    }


                    if (
                        $movementRequest->target_employee_id === null
                        && $movementRequest->target_user_id === null
                    ) {

                        throw ValidationException::withMessages([
                            'target_employee_id' =>
                                'گیرنده انتقال مشخص نشده است.',
                        ]);
                    }

                    $targetEmployee = Employee::withoutGlobalScopes()
                        ->with('user')
                        ->when(
                            $movementRequest->target_employee_id !== null,
                            fn ($query) => $query->whereKey($movementRequest->target_employee_id)
                        )
                        ->when(
                            $movementRequest->target_employee_id === null,
                            fn ($query) => $query->where('user_id', $movementRequest->target_user_id)
                        )
                        ->where('company_id', $asset->company_id)
                        ->where('is_active', true)
                        ->whereDoesntHave('user', fn ($query) => $query->where('is_super_admin', true))
                        ->first();

                    if ($targetEmployee === null) {

                        throw ValidationException::withMessages([
                            'target_employee_id' =>
                                'گیرنده انتقال معتبر یا فعال نیست.',
                        ]);
                    }

                    $targetUserId = $targetEmployee->user_id !== null
                        ? (int) $targetEmployee->user_id
                        : null;

                    if (
                        ($targetUserId !== null
                            && $targetUserId === (int) $currentHolderId)
                        || ($beforeCustody['custody_employee_id'] !== null
                            && (int) $beforeCustody['custody_employee_id'] === (int) $targetEmployee->id)
                    ) {

                        throw ValidationException::withMessages([
                            'target_employee_id' =>
                                'دارایی هم‌اکنون در اختیار گیرنده انتخاب‌شده است.',
                        ]);
                    }


                    $transactionData['type'] =
                        'transfer';

                    $transactionData['from_user_id'] =
                        $currentHolderId;

                    $transactionData['to_user_id'] =
                        $targetUserId;

                    $transactionData['to_custody_type'] =
                        AssetCustodyService::TYPE_EMPLOYEE;

                    $transactionData['to_employee_id'] =
                        $targetEmployee->id;

                    $transactionData['to_department_id'] =
                        $targetEmployee->department_id;

                    $transactionData['to_site_id'] =
                        $targetEmployee->site_id;

                    $transactionData['to_location_id'] =
                        $targetEmployee->location_id;

                    $this->custodyService->applyEmployee(
                        $asset,
                        $targetEmployee
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | RETURN
                |--------------------------------------------------------------------------
                */

                elseif (
                    $movementRequest->movement_type
                    ===
                    AssetMovementRequest::TYPE_RETURN
                ) {

                    if (
                        $asset->status
                        !==
                        'assigned'
                    ) {

                        throw ValidationException::withMessages([
                            'asset' =>
                                'دارایی در زمان نهایی‌سازی عودت، تحویل‌شده نیست.',
                        ]);
                    }


                    if (
                        $currentHolderId
                        ===
                        null
                    ) {

                        throw ValidationException::withMessages([
                            'asset' =>
                                'دارنده فعلی دارایی قابل تشخیص نیست.',
                        ]);
                    }


                    $transactionData['type'] =
                        'return';

                    $transactionData['from_user_id'] =
                        $currentHolderId;

                    $transactionData['to_custody_type'] =
                        AssetCustodyService::TYPE_WAREHOUSE;

                    $this->custodyService->applyWarehouse($asset);
                }


                /*
                |--------------------------------------------------------------------------
                | DISPOSAL
                |--------------------------------------------------------------------------
                */

                elseif (
                    $movementRequest->movement_type
                    ===
                    AssetMovementRequest::TYPE_DISPOSAL
                ) {

                    if (
                        $asset->status
                        !==
                        'warehouse'
                    ) {

                        throw ValidationException::withMessages([
                            'asset' =>
                                'برای اجرای اسقاط، دارایی باید در انبار باشد.',
                        ]);
                    }


                    $transactionData['type'] =
                        'destroy';


                    $asset->status =
                        'destroyed';

                    $asset->save();
                }


                else {

                    throw ValidationException::withMessages([
                        'movement_type' =>
                            'نوع درخواست عملیات دارایی معتبر نیست.',
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Persist final transaction
                |--------------------------------------------------------------------------
                */

                AssetTransaction::withoutGlobalScopes()
                    ->create(
                        $transactionData
                    );


                /*
                |--------------------------------------------------------------------------
                | Mark movement completed
                |--------------------------------------------------------------------------
                */

                $movementRequest->update([

                    'status' =>
                        AssetMovementRequest::STATUS_COMPLETED,

                    'completed_at' =>
                        now(),
                ]);
            }
        );
    }


    private function currentHolderId(
        Asset $asset
    ): ?int {

        if (
            $asset->status
            !==
            'assigned'
        ) {
            return null;
        }


        $lastAssignment =
            AssetTransaction::withoutGlobalScopes()
                ->where(
                    'asset_id',
                    $asset->id
                )
                ->whereIn(
                    'type',
                    [
                        'delivery',
                        'transfer',
                    ]
                )
                ->whereNotNull(
                    'to_user_id'
                )
                ->latest('id')
                ->first();


        return $lastAssignment?->to_user_id
        !==
        null

            ? (int) $lastAssignment->to_user_id

            : null;
    }
}
