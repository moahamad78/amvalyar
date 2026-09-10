<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovementRequest;
use App\Models\AssetTransaction;
use App\Models\Employee;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetMovementRequestService
{
    public function __construct(
        private readonly WorkflowRuntimeService $workflowRuntimeService
    ) {
    }


    public function createDraft(
        Asset $asset,
        string $movementType,
        User $requesterUser,
        ?Employee $requesterEmployee = null,
        ?int $targetUserId = null,
        ?string $reason = null,
        ?string $notes = null,
        ?int $targetEmployeeId = null
    ): AssetMovementRequest {

        $this->validateRequester(
            $asset,
            $requesterUser,
            $requesterEmployee
        );

        $this->validateMovementType(
            $movementType
        );

        $this->validateAssetState(
            $asset,
            $movementType
        );


        $targetEmployee =
            $this->resolveTargetEmployee(
                asset:
                    $asset,

                movementType:
                    $movementType,

                targetEmployeeId:
                    $targetEmployeeId,

                targetUserId:
                    $targetUserId
            );

        $targetUser = $targetEmployee?->user;


        $this->ensureNoOpenRequest(
            $asset
        );


        return AssetMovementRequest::withoutGlobalScopes()
            ->create([

                'company_id' =>
                    $asset->company_id,

                'asset_id' =>
                    $asset->id,

                'movement_type' =>
                    $movementType,

                'target_custody_type' =>
                    $targetEmployee !== null ? 'employee' : null,

                'target_user_id' =>
                    $targetUser?->id,

                'target_employee_id' =>
                    $targetEmployee?->id,

                'requested_by_user_id' =>
                    $requesterUser->id,

                'requested_by_employee_id' =>
                    $requesterEmployee?->id,

                'status' =>
                    AssetMovementRequest::STATUS_DRAFT,

                'reason' =>
                    $reason,

                'notes' =>
                    $notes,
            ]);
    }


    public function submit(
        AssetMovementRequest $movementRequest
    ): AssetMovementRequest {

        return DB::transaction(
            function () use (
                $movementRequest
            ): AssetMovementRequest {

                $locked =
                    AssetMovementRequest::withoutGlobalScopes()
                        ->whereKey(
                            $movementRequest->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                if (
                    $locked->status
                    !==
                    AssetMovementRequest::STATUS_DRAFT
                ) {

                    throw ValidationException::withMessages([
                        'movement_request' =>
                            'فقط درخواست پیش‌نویس قابل ارسال است.',
                    ]);
                }


                $asset =
                    Asset::withoutGlobalScopes()
                        ->whereKey(
                            $locked->asset_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                if (
                    (int) $asset->company_id
                    !==
                    (int) $locked->company_id
                ) {

                    throw ValidationException::withMessages([
                        'asset' =>
                            'شرکت دارایی با شرکت درخواست یکسان نیست.',
                    ]);
                }


                $this->validateAssetState(
                    $asset,
                    $locked->movement_type
                );


                $requesterUser =
                    User::withoutGlobalScopes()
                        ->findOrFail(
                            $locked->requested_by_user_id
                        );


                $requesterEmployee =
                    $locked->requested_by_employee_id
                    !== null

                        ? Employee::withoutGlobalScopes()
                            ->find(
                                $locked->requested_by_employee_id
                            )

                        : null;


                $processType =
                    $this->processTypeFor(
                        $locked->movement_type
                    );


                $workflow =
                    Workflow::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $locked->company_id
                        )
                        ->where(
                            'process_type',
                            $processType
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderByDesc(
                            'is_default'
                        )
                        ->orderByDesc(
                            'version'
                        )
                        ->orderByDesc(
                            'id'
                        )
                        ->first();


                if (
                    $workflow
                    ===
                    null
                ) {

                    throw ValidationException::withMessages([
                        'workflow' =>
                            'برای این نوع عملیات، گردش کاری فعال تعریف نشده است.',
                    ]);
                }


                $instance =
                    $this->workflowRuntimeService
                        ->start(

                            workflow:
                                $workflow,

                            requesterEmployee:
                                $requesterEmployee,

                            requesterUser:
                                $requesterUser,

                            subjectType:
                                AssetMovementRequest::class,

                            subjectId:
                                $locked->id,

                            context: [

                                'asset_id' =>
                                    $asset->id,

                                'movement_type' =>
                                    $locked->movement_type,

                                'target_user_id' =>
                                    $locked->target_user_id,

                                'target_employee_id' =>
                                    $locked->target_employee_id,

                                'reason' =>
                                    $locked->reason,
                            ]
                        );


                $locked->update([

                    'workflow_instance_id' =>
                        $instance->id,

                    'status' =>
                        AssetMovementRequest::STATUS_SUBMITTED,

                    'submitted_at' =>
                        now(),
                ]);


                return $locked->fresh([
                    'asset',
                    'targetUser',
                    'targetEmployee',
                    'requesterUser',
                    'requesterEmployee',
                    'workflowInstance',
                ]);
            }
        );
    }


    public function processTypeFor(
        string $movementType
    ): string {

        return match (
            $movementType
        ) {

            AssetMovementRequest::TYPE_TRANSFER =>
                'asset_transfer',

            AssetMovementRequest::TYPE_RETURN =>
                'asset_return',

            AssetMovementRequest::TYPE_DISPOSAL =>
                'asset_disposal',

            default =>
                throw ValidationException::withMessages([
                    'movement_type' =>
                        'نوع عملیات دارایی معتبر نیست.',
                ]),
        };
    }


    private function validateMovementType(
        string $movementType
    ): void {

        if (
            !in_array(
                $movementType,
                [
                    AssetMovementRequest::TYPE_TRANSFER,
                    AssetMovementRequest::TYPE_RETURN,
                    AssetMovementRequest::TYPE_DISPOSAL,
                ],
                true
            )
        ) {

            throw ValidationException::withMessages([
                'movement_type' =>
                    'نوع عملیات دارایی معتبر نیست.',
            ]);
        }
    }


    private function validateRequester(
        Asset $asset,
        User $requesterUser,
        ?Employee $requesterEmployee
    ): void {

        if (
            !$requesterUser->isSuperAdmin()
            &&
            (int) $requesterUser->company_id
            !==
            (int) $asset->company_id
        ) {

            throw ValidationException::withMessages([
                'requester' =>
                    'کاربر درخواست‌کننده متعلق به شرکت این دارایی نیست.',
            ]);
        }


        if (
            $requesterEmployee !== null
            &&
            (int) $requesterEmployee->company_id
            !==
            (int) $asset->company_id
        ) {

            throw ValidationException::withMessages([
                'requester_employee' =>
                    'پرسنل درخواست‌کننده متعلق به شرکت این دارایی نیست.',
            ]);
        }
    }


    private function validateAssetState(
        Asset $asset,
        string $movementType
    ): void {

        match (
            $movementType
        ) {

            AssetMovementRequest::TYPE_TRANSFER,
            AssetMovementRequest::TYPE_RETURN =>
                $this->requireAssetStatus(
                    $asset,
                    'assigned',
                    'این عملیات فقط برای دارایی تحویل‌شده مجاز است.'
                ),

            AssetMovementRequest::TYPE_DISPOSAL =>
                $this->requireAssetStatus(
                    $asset,
                    'warehouse',
                    'برای درخواست اسقاط، دارایی باید ابتدا در انبار باشد.'
                ),

            default =>
                throw ValidationException::withMessages([
                    'movement_type' =>
                        'نوع عملیات دارایی معتبر نیست.',
                ]),
        };
    }


    private function requireAssetStatus(
        Asset $asset,
        string $requiredStatus,
        string $message
    ): void {

        if (
            $asset->status
            !==
            $requiredStatus
        ) {

            throw ValidationException::withMessages([
                'asset' =>
                    $message,
            ]);
        }
    }


    private function resolveTargetEmployee(
        Asset $asset,
        string $movementType,
        ?int $targetEmployeeId,
        ?int $targetUserId
    ): ?Employee {

        if (
            $movementType
            !==
            AssetMovementRequest::TYPE_TRANSFER
        ) {

            if ($targetEmployeeId !== null || $targetUserId !== null) {

                throw ValidationException::withMessages([
                    'target_employee_id' =>
                        'گیرنده فقط برای انتقال دارایی قابل انتخاب است.',
                ]);
            }


            return null;
        }


        if ($targetEmployeeId === null && $targetUserId === null) {

            throw ValidationException::withMessages([
                'target_employee_id' =>
                    'انتخاب گیرنده برای انتقال دارایی الزامی است.',
            ]);
        }

        $targetEmployee = Employee::withoutGlobalScopes()
                ->with('user')
                ->when($targetEmployeeId !== null, fn ($query) => $query->whereKey($targetEmployeeId))
                ->when($targetEmployeeId === null, function ($query) use ($targetUserId): void {
                    $query->where('user_id', $targetUserId);
                })
                ->where(
                    'company_id',
                    $asset->company_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if ($targetEmployee === null) {

            throw ValidationException::withMessages([
                'target_employee_id' =>
                    'گیرنده معتبر و فعال از شرکت دارایی انتخاب نشده است.',
            ]);
        }

        $currentHolderId = $this->currentHolderId($asset);
        $currentHolderEmployeeId = $this->currentHolderEmployeeId($asset);

        if (($currentHolderId !== null && $targetEmployee->user_id !== null
                && (int) $currentHolderId === (int) $targetEmployee->user_id)
            || ($currentHolderEmployeeId !== null
                && (int) $currentHolderEmployeeId === (int) $targetEmployee->id)) {

            throw ValidationException::withMessages([
                'target_employee_id' =>
                    'دارایی هم‌اکنون در اختیار همین پرسنل است.',
            ]);
        }

        return $targetEmployee;
    }

    private function currentHolderEmployeeId(Asset $asset): ?int
    {
        if ($asset->status !== 'assigned') {
            return null;
        }

        $lastAssignment = AssetTransaction::withoutGlobalScopes()
            ->where('asset_id', $asset->id)
            ->whereIn('type', ['delivery', 'transfer'])
            ->whereNotNull('to_employee_id')
            ->latest('id')
            ->first();

        return $lastAssignment?->to_employee_id !== null
            ? (int) $lastAssignment->to_employee_id
            : ($asset->custody_employee_id !== null
                ? (int) $asset->custody_employee_id
                : null);
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
                ->latest(
                    'id'
                )
                ->first();


        return $lastAssignment?->to_user_id
        !==
        null

            ? (int) $lastAssignment->to_user_id

            : null;
    }


    private function ensureNoOpenRequest(
        Asset $asset
    ): void {

        $exists =
            AssetMovementRequest::withoutGlobalScopes()
                ->where(
                    'asset_id',
                    $asset->id
                )
                ->whereIn(
                    'status',
                    [
                        AssetMovementRequest::STATUS_DRAFT,
                        AssetMovementRequest::STATUS_SUBMITTED,
                        AssetMovementRequest::STATUS_APPROVED,
                    ]
                )
                ->exists();


        if ($exists) {

            throw ValidationException::withMessages([
                'asset' =>
                    'برای این دارایی یک درخواست عملیاتی باز وجود دارد.',
            ]);
        }
    }
}
