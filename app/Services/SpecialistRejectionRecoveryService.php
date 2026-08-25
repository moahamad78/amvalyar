<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\InventoryRequestItem;
use App\Models\User;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceBranchItem;
use App\Models\WorkflowInstanceStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SpecialistRejectionRecoveryService
{
    public function __construct(
        private readonly InventoryAssetAllocationService $allocationService
    ) {
    }


    public function rejectForReplacement(
        WorkflowInstanceBranch $branch,
        ?Employee $actorEmployee,
        User $actorUser,
        string $comment
    ): WorkflowInstanceBranch {

        return DB::transaction(
            function () use (
                $branch,
                $actorEmployee,
                $actorUser,
                $comment
            ): WorkflowInstanceBranch {

                $lockedBranch =
                    WorkflowInstanceBranch::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $branch->id
                        );


                if (
                    $lockedBranch->status
                    !==
                    'pending'
                ) {

                    throw ValidationException::withMessages([
                        'branch' =>
                            'این تأیید تخصصی دیگر در وضعیت قابل رد نیست.',
                    ]);
                }


                $branchItems =
                    WorkflowInstanceBranchItem::query()
                        ->where(
                            'workflow_instance_branch_id',
                            $lockedBranch->id
                        )
                        ->lockForUpdate()
                        ->get();


                if (
                    $branchItems->isEmpty()
                ) {

                    throw ValidationException::withMessages([
                        'branch' =>
                            'هیچ دارایی برای این مسیر تخصصی ثبت نشده است.',
                    ]);
                }


                $released = [];


                foreach (
                    $branchItems
                    as
                    $branchItem
                ) {

                    $allocation =
                        InventoryRequestAllocation::query()
                            ->where(
                                'inventory_request_item_id',
                                $branchItem
                                    ->inventory_request_item_id
                            )
                            ->where(
                                'asset_id',
                                $branchItem->asset_id
                            )
                            ->whereIn(
                                'status',
                                [
                                    'reserved',
                                    'approved',
                                ]
                            )
                            ->lockForUpdate()
                            ->first();


                    if (
                        $allocation
                        ===
                        null
                    ) {

                        throw ValidationException::withMessages([
                            'allocation' =>
                                'تخصیص فعال یکی از دارایی‌های این مسیر تخصصی پیدا نشد.',
                        ]);
                    }


                    $released[] = [

                        'branch_item_id' =>
                            $branchItem->id,

                        'inventory_request_item_id' =>
                            $branchItem
                                ->inventory_request_item_id,

                        'old_asset_id' =>
                            $branchItem->asset_id,

                        'allocation_id' =>
                            $allocation->id,
                    ];


                    $this->allocationService
                        ->release(
                            $allocation
                        );
                }


                $settings =
                    is_array(
                        $lockedBranch->settings
                    )
                        ? $lockedBranch->settings
                        : [];


                $history =
                    $settings[
                        'recovery_history'
                    ]
                    ??
                    [];


                $history[] = [

                    'event' =>
                        'specialist_rejected',

                    'at' =>
                        now()->toIso8601String(),

                    'employee_id' =>
                        $actorEmployee?->id,

                    'user_id' =>
                        $actorUser->id,

                    'comment' =>
                        $comment,

                    'released' =>
                        $released,
                ];


                $settings[
                    'recovery_history'
                ] =
                    $history;


                $settings[
                    'recovery_state'
                ] =
                    'waiting_warehouse_replacement';


                $lockedBranch->update([

                    'status' =>
                        'rejected',

                    'acted_at' =>
                        now(),

                    'acted_by_employee_id' =>
                        $actorEmployee?->id,

                    'acted_by_user_id' =>
                        $actorUser->id,

                    'comment' =>
                        $comment,

                    'settings' =>
                        $settings,
                ]);


                return $lockedBranch->fresh();
            }
        );
    }


    public function replaceAssets(
        WorkflowInstanceBranch $branch,
        array $replacements,
        ?Employee $warehouseEmployee,
        User $warehouseUser
    ): WorkflowInstanceBranch {

        return DB::transaction(
            function () use (
                $branch,
                $replacements,
                $warehouseEmployee,
                $warehouseUser
            ): WorkflowInstanceBranch {

                $lockedBranch =
                    WorkflowInstanceBranch::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $branch->id
                        );


                if (
                    $lockedBranch->status
                    !==
                    'rejected'
                ) {

                    throw ValidationException::withMessages([
                        'branch' =>
                            'این مسیر تخصصی در انتظار اصلاح انبار نیست.',
                    ]);
                }


                $this->ensureWarehouseActor(
                    $lockedBranch,
                    $warehouseEmployee,
                    $warehouseUser
                );


                $instance =
                    $lockedBranch->instance()
                        ->firstOrFail();


                if (
                    $instance->subject_type
                    !==
                    InventoryRequest::class
                    ||
                    $instance->subject_id
                    ===
                    null
                ) {

                    throw ValidationException::withMessages([
                        'request' =>
                            'درخواست کالای مرتبط با این مسیر پیدا نشد.',
                    ]);
                }


                $inventoryRequest =
                    InventoryRequest::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $instance->subject_id
                        );


                $branchItems =
                    WorkflowInstanceBranchItem::query()
                        ->where(
                            'workflow_instance_branch_id',
                            $lockedBranch->id
                        )
                        ->lockForUpdate()
                        ->get();


                if (
                    $branchItems->isEmpty()
                ) {

                    throw ValidationException::withMessages([
                        'branch' =>
                            'هیچ دارایی برای اصلاح این مسیر ثبت نشده است.',
                    ]);
                }


                $expectedIds =
                    $branchItems
                        ->pluck('id')
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->sort()
                        ->values()
                        ->all();


                $submittedIds =
                    collect(
                        array_keys(
                            $replacements
                        )
                    )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->sort()
                        ->values()
                        ->all();


                if (
                    $expectedIds
                    !==
                    $submittedIds
                ) {

                    throw ValidationException::withMessages([
                        'replacements' =>
                            'برای همه دارایی‌های ردشده باید جایگزین انتخاب شود.',
                    ]);
                }


                $replacementAssetIds =
                    collect(
                        array_values(
                            $replacements
                        )
                    )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        );


                if (
                    $replacementAssetIds
                        ->unique()
                        ->count()
                    !==
                    $replacementAssetIds
                        ->count()
                ) {

                    throw ValidationException::withMessages([
                        'replacements' =>
                            'یک دارایی را نمی‌توان برای چند قلم به‌صورت همزمان انتخاب کرد.',
                    ]);
                }


                $replacementHistory =
                    [];


                foreach (
                    $branchItems
                    as
                    $branchItem
                ) {

                    $replacementAssetId =
                        (int) (
                            $replacements[
                                $branchItem->id
                            ]
                            ??
                            0
                        );


                    $replacementAsset =
                        Asset::withoutGlobalScopes()
                            ->whereKey(
                                $replacementAssetId
                            )
                            ->where(
                                'company_id',
                                $lockedBranch->company_id
                            )
                            ->where(
                                'asset_category_id',
                                $lockedBranch
                                    ->asset_category_id
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->where(
                                'status',
                                'warehouse'
                            )
                            ->lockForUpdate()
                            ->first();


                    if (
                        $replacementAsset
                        ===
                        null
                    ) {

                        throw ValidationException::withMessages([
                            'replacements.' .
                            $branchItem->id =>
                                'دارایی جایگزین معتبر نیست یا در انبار قابل تخصیص نیست.',
                        ]);
                    }


                    $requestItem =
                        InventoryRequestItem::query()
                            ->whereKey(
                                $branchItem
                                    ->inventory_request_item_id
                            )
                            ->firstOrFail();


                    $oldAssetId =
                        (int) $branchItem->asset_id;


                    $newAllocation =
                        $this->allocationService
                            ->reserve(
                                $inventoryRequest,
                                $requestItem,
                                $replacementAsset,
                                $warehouseUser
                            );


                    $newAllocation->status =
                        'approved';

                    $newAllocation->approved_at =
                        now();

                    $newAllocation->save();


                    $branchItem->update([
                        'asset_id' =>
                            $replacementAsset->id,
                    ]);


                    $replacementHistory[] = [

                        'branch_item_id' =>
                            $branchItem->id,

                        'request_item_id' =>
                            $requestItem->id,

                        'old_asset_id' =>
                            $oldAssetId,

                        'new_asset_id' =>
                            $replacementAsset->id,

                        'new_allocation_id' =>
                            $newAllocation->id,
                    ];
                }


                $settings =
                    is_array(
                        $lockedBranch->settings
                    )
                        ? $lockedBranch->settings
                        : [];


                $history =
                    $settings[
                        'recovery_history'
                    ]
                    ??
                    [];


                $history[] = [

                    'event' =>
                        'warehouse_replaced_assets',

                    'at' =>
                        now()->toIso8601String(),

                    'employee_id' =>
                        $warehouseEmployee?->id,

                    'user_id' =>
                        $warehouseUser->id,

                    'replacements' =>
                        $replacementHistory,
                ];


                $settings[
                    'recovery_history'
                ] =
                    $history;


                $settings[
                    'recovery_state'
                ] =
                    'specialist_recheck';


                $lockedBranch->update([

                    'status' =>
                        'pending',

                    'activated_at' =>
                        now(),

                    'acted_at' =>
                        null,

                    'acted_by_employee_id' =>
                        null,

                    'acted_by_user_id' =>
                        null,

                    'comment' =>
                        null,

                    'settings' =>
                        $settings,
                ]);


                return $lockedBranch->fresh();
            }
        );
    }


    public function availableAssets(
        WorkflowInstanceBranch $branch
    ): Collection {

        return $this->allocationService
            ->availableAssets(
                (int) $branch->company_id
            )
            ->where(
                'asset_category_id',
                $branch->asset_category_id
            )
            ->get();
    }


    private function ensureWarehouseActor(
        WorkflowInstanceBranch $branch,
        ?Employee $employee,
        User $user
    ): void {

        if (
            $user->isSuperAdmin()
        ) {

            return;
        }


        $warehouseStep =
            WorkflowInstanceStep::query()
                ->findOrFail(
                    $branch->parent_step_id
                );


        $userMatches =
            $warehouseStep->resolved_user_id
            !==
            null
            &&
            (int) $warehouseStep->resolved_user_id
            ===
            (int) $user->id;


        $employeeMatches =
            $employee
            !==
            null
            &&
            $warehouseStep->resolved_employee_id
            !==
            null
            &&
            (int) $warehouseStep->resolved_employee_id
            ===
            (int) $employee->id;


        if (
            !$userMatches
            &&
            !$employeeMatches
        ) {

            throw ValidationException::withMessages([
                'actor' =>
                    'فقط انباردار مسئول این درخواست می‌تواند دارایی جایگزین انتخاب کند.',
            ]);
        }
    }
}