<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\DeliveryDispute;
use App\Models\DeliveryDisputeItem;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeliveryDisputeReplacementDeliveryService
{
    public function __construct(
        private readonly AssetCustodyService $custodyService
    ) {
    }

    /**
     * Physically re-deliver the approved replacement SET.
     *
     * This does not approve REQUESTER-RECEIPT. It only moves the real assets,
     * writes delivery transactions through the custody engine, marks the new
     * allocations delivered, and returns the request to awaiting_receipt.
     */
    public function deliver(
        DeliveryDispute $deliveryDispute,
        User $actorUser,
        ?Employee $actorEmployee,
        ?string $note = null
    ): void {
        DB::transaction(
            function () use (
                $deliveryDispute,
                $actorUser,
                $actorEmployee,
                $note
            ): void {
                $dispute =
                    DeliveryDispute::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $deliveryDispute->id
                        );

                if (
                    $dispute->status
                    !==
                    'replacement_review_approved'
                ) {
                    throw ValidationException::withMessages([
                        'replacement_delivery' =>
                            'جایگزین‌ها هنوز برای تحویل مجدد نهایی نشده‌اند.',
                    ]);
                }

                $inventoryRequest =
                    InventoryRequest::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $dispute->inventory_request_id
                        );

                if (
                    $inventoryRequest->status
                    !==
                    'replacement_ready_delivery'
                ) {
                    throw ValidationException::withMessages([
                        'replacement_delivery' =>
                            'درخواست در وضعیت آماده تحویل مجدد نیست.',
                    ]);
                }

                if (
                    (int) $inventoryRequest->company_id
                    !==
                    (int) $dispute->company_id
                ) {
                    abort(404);
                }

                $this->ensureWarehouseActor(
                    actorUser:
                        $actorUser,

                    actorEmployee:
                        $actorEmployee,

                    dispute:
                        $dispute,
                );

                $receiptStep =
                    WorkflowInstanceStep::query()
                        ->whereKey(
                            $dispute->requester_receipt_step_id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $receiptStep
                    ===
                    null
                    ||
                    $receiptStep->code
                    !==
                    'REQUESTER-RECEIPT'
                    ||
                    $receiptStep->status
                    !==
                    'pending'
                ) {
                    throw ValidationException::withMessages([
                        'replacement_delivery' =>
                            'مرحله تأیید دریافت درخواست‌کننده فعال و در انتظار نیست.',
                    ]);
                }

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $receiptStep->workflow_instance_id
                        );

                if (
                    (int) $instance->current_step_id
                    !==
                    (int) $receiptStep->id
                ) {
                    throw ValidationException::withMessages([
                        'replacement_delivery' =>
                            'مرحله تأیید دریافت، مرحله جاری گردش کاری نیست.',
                    ]);
                }

                $items =
                    DeliveryDisputeItem::withoutGlobalScopes()
                        ->with([
                            'replacementAsset',
                            'replacementAllocation',
                        ])
                        ->where(
                            'delivery_dispute_id',
                            $dispute->id
                        )
                        ->where(
                            'status',
                            'replacement_approved'
                        )
                        ->lockForUpdate()
                        ->get();

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'replacement_delivery' =>
                            'هیچ کالای جایگزین تأییدشده‌ای برای تحویل وجود ندارد.',
                    ]);
                }

                $allItems =
                    DeliveryDisputeItem::withoutGlobalScopes()
                        ->where(
                            'delivery_dispute_id',
                            $dispute->id
                        )
                        ->count();

                if (
                    $items->count()
                    !==
                    $allItems
                ) {
                    throw ValidationException::withMessages([
                        'replacement_delivery' =>
                            'همه اقلام مغایرت باید قبل از تحویل مجدد، جایگزین تأییدشده داشته باشند.',
                    ]);
                }

                $targetType =
                    $inventoryRequest->delivery_target_type
                    ?:
                    'employee';

                if (
                    !in_array(
                        $targetType,
                        [
                            'employee',
                            'organization',
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'delivery_target_type' =>
                            'نوع مقصد تحویل درخواست معتبر نیست.',
                    ]);
                }

                $recipientUser =
                    null;

                $recipientEmployee =
                    null;

                $targetDepartment =
                    null;

                $targetSite =
                    null;

                $targetLocation =
                    null;

                if (
                    $targetType
                    ===
                    'employee'
                ) {
                    if (
                        $inventoryRequest->requester_user_id
                        ===
                        null
                    ) {
                        throw ValidationException::withMessages([
                            'requester' =>
                                'کاربر درخواست‌کننده برای تحویل مجدد قابل تشخیص نیست.',
                        ]);
                    }

                    $recipientUser =
                        User::withoutGlobalScopes()
                            ->where(
                                'company_id',
                                $inventoryRequest->company_id
                            )
                            ->whereKey(
                                $inventoryRequest->requester_user_id
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->first();

                    if ($recipientUser === null) {
                        throw ValidationException::withMessages([
                            'requester' =>
                                'حساب کاربری درخواست‌کننده فعال نیست.',
                        ]);
                    }

                    $recipientEmployee =
                        $inventoryRequest->requester_employee_id
                        !==
                        null
                            ? Employee::withoutGlobalScopes()
                                ->where(
                                    'company_id',
                                    $inventoryRequest->company_id
                                )
                                ->whereKey(
                                    $inventoryRequest->requester_employee_id
                                )
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->first()
                            : Employee::withoutGlobalScopes()
                                ->where(
                                    'company_id',
                                    $inventoryRequest->company_id
                                )
                                ->where(
                                    'user_id',
                                    $recipientUser->id
                                )
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->first();

                    if ($recipientEmployee === null) {
                        throw ValidationException::withMessages([
                            'requester' =>
                                'رکورد پرسنلی فعال درخواست‌کننده برای تحویل مجدد پیدا نشد.',
                        ]);
                    }
                }
                else {
                    [
                        $targetDepartment,
                        $targetSite,
                        $targetLocation,
                    ] =
                        $this->resolveOrganizationTarget(
                            $inventoryRequest
                        );
                }

                foreach ($items as $item) {
                    $asset =
                        $item->replacementAsset;

                    $allocation =
                        $item->replacementAllocation;

                    if (
                        $asset === null
                        ||
                        $allocation === null
                    ) {
                        throw ValidationException::withMessages([
                            'replacement_delivery' =>
                                'ارتباط دارایی یا تخصیص جایگزین یکی از اقلام ناقص است.',
                        ]);
                    }

                    if (
                        (int) $asset->company_id
                        !==
                        (int) $dispute->company_id
                        ||
                        (int) $allocation->company_id
                        !==
                        (int) $dispute->company_id
                        ||
                        (int) $allocation->inventory_request_id
                        !==
                        (int) $inventoryRequest->id
                        ||
                        (int) $allocation->asset_id
                        !==
                        (int) $asset->id
                    ) {
                        abort(404);
                    }

                    if (
                        $allocation->status
                        !==
                        'approved'
                    ) {
                        throw ValidationException::withMessages([
                            'replacement_delivery' =>
                                'تخصیص جایگزین یکی از اقلام در وضعیت قابل تحویل نیست.',
                        ]);
                    }

                    if (
                        $asset->status
                        !==
                        'warehouse'
                        ||
                        $asset->custody_type
                        !==
                        AssetCustodyService::TYPE_WAREHOUSE
                    ) {
                        throw ValidationException::withMessages([
                            'replacement_delivery' =>
                                'دارایی جایگزین باید هنگام تحویل واقعاً در انبار باشد.',
                        ]);
                    }

                    if (
                        $asset->asset_code
                        ===
                        null
                        ||
                        trim(
                            (string) $asset->asset_code
                        )
                        ===
                        ''
                    ) {
                        throw ValidationException::withMessages([
                            'replacement_delivery' =>
                                'دارایی جایگزین قبل از تحویل مجدد باید کد دائمی اموال داشته باشد.',
                        ]);
                    }

                    $description =
                        'تحویل مجدد کالای جایگزین بابت مغایرت درخواست '
                        . $inventoryRequest->request_number
                        . (
                            $note
                            !==
                            null
                            &&
                            trim($note)
                            !==
                            ''
                                ? ' - ' . trim($note)
                                : ''
                        );

                    if (
                        $targetType
                        ===
                        'employee'
                    ) {
                        $this->custodyService
                            ->assignToEmployee(
                                asset:
                                    $asset,

                                employee:
                                    $recipientEmployee,

                                user:
                                    $recipientUser,

                                actorUser:
                                    $actorUser,

                                description:
                                    $description,
                            );
                    }
                    else {
                        $this->custodyService
                            ->assignToOrganization(
                                asset:
                                    $asset,

                                department:
                                    $targetDepartment,

                                location:
                                    $targetLocation,

                                site:
                                    $targetSite,

                                actorUser:
                                    $actorUser,

                                description:
                                    $description,
                            );
                    }

                    $allocation->update([
                        'status' =>
                            'delivered',

                        'delivered_at' =>
                            now(),

                        'note' =>
                            trim(
                                (
                                    $allocation->note
                                        ? $allocation->note
                                            . PHP_EOL
                                        : ''
                                )
                                . 'تحویل مجدد جایگزین پس از تأیید تخصصی'
                                . (
                                    $note
                                    !==
                                    null
                                    &&
                                    trim($note)
                                    !==
                                    ''
                                        ? ': ' . trim($note)
                                        : ''
                                )
                            ),
                    ]);

                    $item->update([
                        'status' =>
                            'replacement_delivered',
                    ]);
                }

                /*
                 * Do NOT increment InventoryRequestItem.fulfilled_quantity.
                 * The original delivery already fulfilled the requested
                 * quantity; replacement changes the physical asset identity,
                 * not the requested quantity.
                 */
                $dispute->update([
                    'status' =>
                        'replacement_delivered',
                ]);

                $inventoryRequest->update([
                    'status' =>
                        'awaiting_receipt',

                    'fulfilled_at' =>
                        null,
                ]);
            }
        );
    }

    private function resolveOrganizationTarget(
        InventoryRequest $inventoryRequest
    ): array {
        $department =
            $inventoryRequest->target_department_id
            !==
            null
                ? Department::withoutGlobalScopes()
                    ->where(
                        'company_id',
                        $inventoryRequest->company_id
                    )
                    ->whereKey(
                        $inventoryRequest->target_department_id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->first()
                : null;

        $location =
            $inventoryRequest->target_location_id
            !==
            null
                ? Location::withoutGlobalScopes()
                    ->where(
                        'company_id',
                        $inventoryRequest->company_id
                    )
                    ->whereKey(
                        $inventoryRequest->target_location_id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->first()
                : null;

        $siteId =
            $inventoryRequest->target_site_id
            ??
            $location?->site_id;

        $site =
            $siteId
            !==
            null
                ? Site::withoutGlobalScopes()
                    ->where(
                        'company_id',
                        $inventoryRequest->company_id
                    )
                    ->whereKey(
                        $siteId
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->first()
                : null;

        if (
            $department
            ===
            null
            &&
            $location
            ===
            null
            &&
            $site
            ===
            null
        ) {
            throw ValidationException::withMessages([
                'delivery_target' =>
                    'برای تحویل مجدد سازمانی، مقصد معتبر سازمانی پیدا نشد.',
            ]);
        }

        return [
            $department,
            $site,
            $location,
        ];
    }

    private function ensureWarehouseActor(
        User $actorUser,
        ?Employee $actorEmployee,
        DeliveryDispute $dispute
    ): void {
        if ($actorUser->isSuperAdmin()) {
            return;
        }

        if (
            (int) $actorUser->company_id
            !==
            (int) $dispute->company_id
        ) {
            abort(404);
        }

        if (
            $dispute->workflow_instance_id
            ===
            null
        ) {
            abort(403);
        }

        $allowed =
            WorkflowInstanceStep::query()
                ->where(
                    'workflow_instance_id',
                    $dispute->workflow_instance_id
                )
                ->whereIn(
                    'code',
                    [
                        'WAREHOUSE',
                        'FINAL-WAREHOUSE-DELIVERY',
                    ]
                )
                ->get()
                ->contains(
                    function (
                        WorkflowInstanceStep $step
                    ) use (
                        $actorUser,
                        $actorEmployee
                    ): bool {
                        $userMatches =
                            $step->resolved_user_id
                            !==
                            null
                            &&
                            (int) $step->resolved_user_id
                            ===
                            (int) $actorUser->id;

                        $employeeMatches =
                            $actorEmployee
                            !==
                            null
                            &&
                            $step->resolved_employee_id
                            !==
                            null
                            &&
                            (int) $step->resolved_employee_id
                            ===
                            (int) $actorEmployee->id;

                        return
                            $userMatches
                            ||
                            $employeeMatches;
                    }
                );

        if (!$allowed) {
            abort(403);
        }
    }
}