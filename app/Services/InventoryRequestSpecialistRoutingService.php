<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetCategoryApprovalRoute;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceBranchItem;
use App\Models\WorkflowInstanceStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryRequestSpecialistRoutingService
{
    private const ACTIVE_ALLOCATION_STATUSES = [
        'reserved',
        'approved',
    ];

    public function __construct(
        private readonly WorkflowApproverResolver $approverResolver
    ) {
    }

    /**
     * Build specialist approval branches from the REAL assets
     * selected by warehouse.
     *
     * Important:
     * - request item category is NOT the source of truth.
     * - selected Asset::asset_category_id IS the source of truth.
     * - one category can have one or more configured approval routes.
     * - every configured route becomes an independent parallel branch.
     */
    public function buildForWarehouseSelection(
        WorkflowInstance $instance,
        InventoryRequest $inventoryRequest,
        WorkflowInstanceStep $warehouseStep
    ): Collection {

        return DB::transaction(
            function () use (
                $instance,
                $inventoryRequest,
                $warehouseStep
            ): Collection {

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $instance->id
                        );

                $inventoryRequest =
                    InventoryRequest::withoutGlobalScopes()
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

                $this->guardContext(
                    $instance,
                    $inventoryRequest,
                    $warehouseStep
                );

                $allocations =
                    InventoryRequestAllocation::query()
                        ->with([
                            'asset.category',
                            'requestItem',
                        ])
                        ->where(
                            'inventory_request_id',
                            $inventoryRequest->id
                        )
                        ->whereIn(
                            'status',
                            self::ACTIVE_ALLOCATION_STATUSES
                        )
                        ->lockForUpdate()
                        ->get();

                if ($allocations->isEmpty()) {
                    throw ValidationException::withMessages([
                        'allocations' =>
                            'برای ایجاد مسیرهای تخصصی، انباردار باید ابتدا حداقل یک دارایی واقعی را انتخاب و رزرو کند.',
                    ]);
                }

                /*
                 * desired[branch_key] = [
                 *   route,
                 *   category_id,
                 *   allocations[]
                 * ]
                 */
                $desired = [];

                foreach ($allocations as $allocation) {

                    $asset =
                        $allocation->asset;

                    if ($asset === null) {
                        throw ValidationException::withMessages([
                            'allocations' =>
                                'یکی از تخصیص‌ها به دارایی معتبر متصل نیست.',
                        ]);
                    }

                    if (
                        (int) $asset->company_id
                        !==
                        (int) $inventoryRequest->company_id
                    ) {
                        throw ValidationException::withMessages([
                            'allocations' =>
                                'یکی از دارایی‌های تخصیص‌یافته متعلق به شرکت دیگری است.',
                        ]);
                    }

                    $categoryId =
                        (int) $asset->asset_category_id;

                    if ($categoryId <= 0) {
                        throw ValidationException::withMessages([
                            'allocations' =>
                                'دسته‌بندی یکی از دارایی‌های انتخاب‌شده مشخص نیست.',
                        ]);
                    }

                    /*
                     * NOTE:
                     * We intentionally do NOT read:
                     * $allocation->requestItem->asset_category_id
                     *
                     * The real selected asset decides specialist routing.
                     */
                    $routes =
                        AssetCategoryApprovalRoute::query()
                            ->where(
                                'company_id',
                                $inventoryRequest->company_id
                            )
                            ->where(
                                'asset_category_id',
                                $categoryId
                            )
                            ->where(
                                'process_type',
                                'inventory_request'
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->orderBy(
                                'sort_order'
                            )
                            ->orderBy(
                                'id'
                            )
                            ->get();

                    /*
                     * No configured route = no specialist branch
                     * for this category.
                     */
                    foreach ($routes as $route) {

                        $branchKey =
                            'category:' .
                            $categoryId .
                            ':route:' .
                            $route->id;

                        if (!array_key_exists(
                            $branchKey,
                            $desired
                        )) {
                            $desired[$branchKey] = [
                                'route' =>
                                    $route,

                                'category_id' =>
                                    $categoryId,

                                'allocations' =>
                                    collect(),
                            ];
                        }

                        $desired[$branchKey]['allocations']
                            ->push(
                                $allocation
                            );
                    }
                }

                $branches =
                    collect();

                foreach ($desired as $branchKey => $entry) {

                    $route =
                        $entry['route'];

                    $shadowStep =
                        new WorkflowInstanceStep([
                            'approver_type' =>
                                $route->approver_type,

                            'approver_reference_id' =>
                                $route->approver_reference_id,
                        ]);

                    $resolved =
                        $this->approverResolver
                            ->resolve(
                                $instance,
                                $shadowStep
                            );

                    $resolvedEmployee =
                        $resolved['employee']
                        ?? null;

                    $resolvedUser =
                        $resolved['user']
                        ?? null;

                    if (
                        $resolvedEmployee === null
                        &&
                        $resolvedUser === null
                    ) {
                        throw ValidationException::withMessages([
                            'approver' =>
                                'مسئول واقعی یکی از مسیرهای تخصصی قابل تشخیص نیست.',
                        ]);
                    }

                    $category =
                        $entry['allocations']
                            ->first()
                            ?->asset
                            ?->category;

                    $branch =
                        WorkflowInstanceBranch::query()
                            ->firstOrNew([
                                'workflow_instance_id' =>
                                    $instance->id,

                                'parent_step_id' =>
                                    $warehouseStep->id,

                                'branch_key' =>
                                    $branchKey,
                            ]);

                    /*
                     * Do not silently overwrite an already acted branch.
                     */
                    if (
                        $branch->exists
                        &&
                        in_array(
                            $branch->status,
                            [
                                'approved',
                                'rejected',
                            ],
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'branches' =>
                                'مسیر تخصصی قبلاً اقدام شده و قابل بازسازی خودکار نیست.',
                        ]);
                    }

                    $branch->fill([
                        'company_id' =>
                            $inventoryRequest->company_id,

                        'asset_category_id' =>
                            $entry['category_id'],

                        'name' =>
                            'تأیید تخصصی - ' .
                            (
                                $category?->name
                                ?? ('دسته ' . $entry['category_id'])
                            ),

                        'approver_type' =>
                            $route->approver_type,

                        'approver_reference_id' =>
                            $route->approver_reference_id,

                        'resolved_employee_id' =>
                            $resolvedEmployee?->id,

                        'resolved_user_id' =>
                            $resolvedUser?->id,

                        'status' =>
                            'pending',

                        'is_required' =>
                            (bool) $route->is_required,

                        'sort_order' =>
                            (int) $route->sort_order,

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

                        'settings' => [
                            'source' =>
                                'warehouse_asset_selection',

                            'route_id' =>
                                $route->id,

                            'category_source' =>
                                'asset.asset_category_id',
                        ],
                    ]);

                    $branch->save();

                    $desiredItemKeys =
                        [];

                    foreach (
                        $entry['allocations']
                        as $allocation
                    ) {

                        $requestItemId =
                            $allocation
                                ->inventory_request_item_id;

                        $assetId =
                            $allocation
                                ->asset_id;

                        $key =
                            (string) $requestItemId .
                            ':' .
                            (string) $assetId;

                        $desiredItemKeys[] =
                            $key;

                        WorkflowInstanceBranchItem::query()
                            ->firstOrCreate([
                                'workflow_instance_branch_id' =>
                                    $branch->id,

                                'inventory_request_item_id' =>
                                    $requestItemId,

                                'asset_id' =>
                                    $assetId,
                            ]);
                    }

                    /*
                     * Remove stale links only while branch is still pending.
                     */
                    $branchItems =
                        WorkflowInstanceBranchItem::query()
                            ->where(
                                'workflow_instance_branch_id',
                                $branch->id
                            )
                            ->get();

                    foreach ($branchItems as $branchItem) {

                        $key =
                            (string) $branchItem
                                ->inventory_request_item_id
                            .
                            ':'
                            .
                            (string) $branchItem
                                ->asset_id;

                        if (
                            !in_array(
                                $key,
                                $desiredItemKeys,
                                true
                            )
                        ) {
                            $branchItem->delete();
                        }
                    }

                    $branches->push(
                        $branch->fresh()
                    );
                }

                return $branches;
            }
        );
    }

    public function allRequiredBranchesApproved(
        WorkflowInstance $instance,
        WorkflowInstanceStep $warehouseStep
    ): bool {

        $required =
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

        if (!(clone $required)->exists()) {
            return true;
        }

        return !(clone $required)
            ->where(
                'status',
                '!=',
                'approved'
            )
            ->exists();
    }

    private function guardContext(
        WorkflowInstance $instance,
        InventoryRequest $inventoryRequest,
        WorkflowInstanceStep $warehouseStep
    ): void {

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
            $warehouseStep->code
            !==
            'WAREHOUSE'
        ) {
            throw ValidationException::withMessages([
                'step' =>
                    'مسیریابی تخصصی فقط از مرحله انباردار قابل ایجاد است.',
            ]);
        }
    }
}