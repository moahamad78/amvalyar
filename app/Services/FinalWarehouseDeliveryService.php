<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalWarehouseDeliveryService
{
    public function __construct(
        private readonly AssetCompletenessService $completenessService,
        private readonly AssetCustodyService $custodyService
    ) {
    }


    public function deliver(
        WorkflowInstanceStep $step,
        User $actorUser,
        Request $httpRequest
    ): void {

        $employee =
            $this->resolveEmployee(
                $actorUser
            );


        $instance =
            WorkflowInstance::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail(
                    $step->workflow_instance_id
                );


        $step =
            WorkflowInstanceStep::query()
                ->lockForUpdate()
                ->findOrFail(
                    $step->id
                );


        $this->guardStep(
            $instance,
            $step,
            $actorUser,
            $employee
        );


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
                    'موضوع مرحله تحویل نهایی یک درخواست کالا نیست.',
            ]);
        }


        $inventoryRequest =
            InventoryRequest::withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail(
                    $instance->subject_id
                );


        $deliveryTargetType =
            $inventoryRequest->delivery_target_type
            ?: 'employee';

        if (
            !in_array(
                $deliveryTargetType,
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

        $recipient = null;
        $recipientEmployee = null;
        $targetDepartment = null;
        $targetLocation = null;
        $targetSite = null;

        if ($deliveryTargetType === 'employee') {
            if (
                $inventoryRequest->requester_user_id
                ===
                null
            ) {
                throw ValidationException::withMessages([
                    'requester' =>
                        'کاربر درخواست‌کننده برای تحویل نهایی قابل تشخیص نیست.',
                ]);
            }

            $recipient =
                User::withoutGlobalScopes()
                    ->findOrFail(
                        $inventoryRequest->requester_user_id
                    );

            $recipientEmployee =
                $inventoryRequest->requester_employee_id !== null
                    ? Employee::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $inventoryRequest->company_id
                        )
                        ->find(
                            $inventoryRequest->requester_employee_id
                        )
                    : Employee::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $inventoryRequest->company_id
                        )
                        ->where(
                            'user_id',
                            $recipient->id
                        )
                        ->first();

            if (
                !$recipient->isSuperAdmin()
                &&
                (int) $recipient->company_id
                !==
                (int) $inventoryRequest->company_id
            ) {
                throw ValidationException::withMessages([
                    'requester' =>
                        'درخواست‌کننده متعلق به شرکت این درخواست نیست.',
                ]);
            }
        }
        else {
            $targetDepartment =
                $inventoryRequest->target_department_id !== null
                    ? Department::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $inventoryRequest->company_id
                        )
                        ->find(
                            $inventoryRequest->target_department_id
                        )
                    : null;

            $targetLocation =
                $inventoryRequest->target_location_id !== null
                    ? Location::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $inventoryRequest->company_id
                        )
                        ->find(
                            $inventoryRequest->target_location_id
                        )
                    : null;

            $targetSite =
                $inventoryRequest->target_site_id !== null
                    ? Site::withoutGlobalScopes()
                        ->where(
                            'company_id',
                            $inventoryRequest->company_id
                        )
                        ->find(
                            $inventoryRequest->target_site_id
                        )
                    : null;

            if (
                $targetDepartment === null
                &&
                $targetLocation === null
                &&
                $targetSite === null
            ) {
                throw ValidationException::withMessages([
                    'delivery_target' =>
                        'مقصد سازمانی درخواست برای تحویل نهایی قابل تشخیص نیست.',
                ]);
            }
        }


        $allocations =
            InventoryRequestAllocation::query()
                ->where(
                    'inventory_request_id',
                    $inventoryRequest->id
                )
                ->where(
                    'status',
                    'approved'
                )
                ->lockForUpdate()
                ->get();


        if (
            $allocations->isEmpty()
        ) {

            throw ValidationException::withMessages([
                'assets' =>
                    'هیچ تخصیص تأییدشده‌ای برای تحویل وجود ندارد.',
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Validate all quantities before changing anything
        |--------------------------------------------------------------------------
        */

        foreach (
            $inventoryRequest->items
            as
            $item
        ) {

            $approvedQuantity =
                (int) round(
                    (float) (
                        $item->approved_quantity
                        ??
                        0
                    )
                );


            if (
                $approvedQuantity
                <=
                0
            ) {

                continue;
            }


            $count =
                $allocations
                    ->where(
                        'inventory_request_item_id',
                        $item->id
                    )
                    ->count();


            if (
                $count
                !==
                $approvedQuantity
            ) {

                throw ValidationException::withMessages([
                    'delivery' =>
                        'تعداد اموال آماده تحویل برای «'
                        .
                        $item->item_name
                        .
                        '» معتبر نیست.',
                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Validate every asset before first write
        |--------------------------------------------------------------------------
        */

        $lockedAssets = [];


        foreach (
            $allocations
            as
            $allocation
        ) {

            $asset =
                Asset::withoutGlobalScopes()
                    ->whereKey(
                        $allocation->asset_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();


            if (
                (int) $asset->company_id
                !==
                (int) $inventoryRequest->company_id
            ) {

                throw ValidationException::withMessages([
                    'asset' =>
                        'یکی از دارایی‌ها متعلق به شرکت درخواست نیست.',
                ]);
            }


            if (
                $asset->status
                !==
                'warehouse'
            ) {

                throw ValidationException::withMessages([
                    'asset' =>
                        'دارایی «'
                        .
                        $asset->title
                        .
                        '» دیگر در انبار نیست و قابل تحویل نیست.',
                ]);
            }


            $completeness =
                $this->completenessService
                    ->check(
                        $asset,
                        AssetCompletenessService::CONTEXT_BEFORE_DELIVERY
                    );


            if (
                !$completeness['complete']
            ) {

                throw ValidationException::withMessages([
                    'asset' =>
                        'دارایی «'
                        .
                        $asset->title
                        .
                        '» برای تحویل کامل نیست: '
                        .
                        implode(
                            'طŒ ',
                            $completeness['missing']
                        ),
                ]);
            }


            /*
             * permanent_asset_code_required_before_final_delivery_v2
             *
             * Defense in depth:
             * before_delivery completeness already requires the permanent
             * asset code, but final delivery protects itself explicitly too.
             */
            if (
                trim(
                    (string) (
                        $asset->asset_code
                        ?? ''
                    )
                )
                ===
                ''
            ) {

                throw ValidationException::withMessages([
                    'asset' =>
                        'دارایی «'
                        .
                        $asset->title
                        .
                        '» کد دائمی اموال ندارد و قابل تحویل نیست.',
                ]);
            }

            if (
                empty(
                    $asset->asset_code
                )
            ) {

                throw ValidationException::withMessages([
                    'asset' =>
                        'دارایی «'
                        .
                        $asset->title
                        .
                        '» پلاک اموال ندارد.',
                ]);
            }


            $lockedAssets[
                $allocation->id
            ] =
                $asset;
        }


        /*
        |--------------------------------------------------------------------------
        | Perform delivery
        |--------------------------------------------------------------------------
        */

        foreach (
            $allocations
            as
            $allocation
        ) {

            /** @var Asset $asset */
            $asset =
                $lockedAssets[
                    $allocation->id
                ];
            if ($deliveryTargetType === 'organization') {
                $this->custodyService->assignToOrganization(
                    asset: $asset,
                    department: $targetDepartment,
                    location: $targetLocation,
                    site: $targetSite,
                    actorUser: $actorUser,
                    description:
                        'استقرار سازمانی بابت درخواست '
                        . $inventoryRequest->request_number
                );
            }
            else {
                $this->custodyService->assignToEmployee(
                    asset: $asset,
                    employee: $recipientEmployee,
                    user: $recipient,
                    actorUser: $actorUser,
                    description:
                        'تحویل نهایی بابت درخواست '
                        . $inventoryRequest->request_number
                );
            }


            $allocation->update([
                'status' =>
                    'delivered',

                'delivered_at' =>
                    now(),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Fulfilled quantities
        |--------------------------------------------------------------------------
        */

        foreach (
            $inventoryRequest->items
            as
            $item
        ) {

            $deliveredCount =
                InventoryRequestAllocation::query()
                    ->where(
                        'inventory_request_id',
                        $inventoryRequest->id
                    )
                    ->where(
                        'inventory_request_item_id',
                        $item->id
                    )
                    ->where(
                        'status',
                        'delivered'
                    )
                    ->count();


            $update = [
                'fulfilled_quantity' =>
                    $deliveredCount,
            ];


            $approvedQuantity =
                (int) round(
                    (float) (
                        $item->approved_quantity
                        ??
                        0
                    )
                );


            if (
                $approvedQuantity
                >
                0
                &&
                $deliveredCount
                >=
                $approvedQuantity
            ) {

                $update['status'] =
                    'fulfilled';
            }


            $item->update(
                $update
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Request fulfilled
        |--------------------------------------------------------------------------
        */

        $inventoryRequest->update([

            'status' =>
                'fulfilled',

            'fulfilled_at' =>
                now(),
        ]);
    }


    private function guardStep(
        WorkflowInstance $instance,
        WorkflowInstanceStep $step,
        User $actorUser,
        ?Employee $actorEmployee
    ): void {

        if (
            $step->code
            !==
            'FINAL-WAREHOUSE-DELIVERY'
            ||
            $step->status
            !==
            'pending'
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله تحویل نهایی در وضعیت معتبر نیست.',
            ]);
        }


        if (
            (int) $instance->current_step_id
            !==
            (int) $step->id
        ) {

            throw ValidationException::withMessages([
                'step' =>
                    'مرحله تحویل نهایی، مرحله جاری گردش کاری نیست.',
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
                    'کاربر اقدام‌کننده متعلق به شرکت این درخواست نیست.',
            ]);
        }


        if (
            $actorUser->isSuperAdmin()
        ) {

            return;
        }


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


        if (
            !$userMatches
            &&
            !$employeeMatches
        ) {

            throw ValidationException::withMessages([
                'actor' =>
                    'فقط انباردار مسئول این درخواست می‌تواند تحویل نهایی را ثبت کند.',
            ]);
        }
    }


    private function resolveEmployee(
        User $user
    ): ?Employee {

        if (
            $user->company_id
            ===
            null
        ) {

            return null;
        }


        return Employee::withoutGlobalScopes()
            ->where(
                'company_id',
                $user->company_id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->first();
    }
}