<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\InventoryRequestItem;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FinalWarehouseDeliveryController extends Controller
{
    public function index(
        Request $request
    ): View {

        $user =
            $request->user();

        $employee =
            $this->resolveEmployee(
                $user
            );


        $steps =
            WorkflowInstanceStep::query()
                ->where(
                    'code',
                    'FINAL-WAREHOUSE-DELIVERY'
                )
                ->where(
                    'status',
                    'pending'
                )
                ->orderBy(
                    'activated_at'
                )
                ->orderBy('id')
                ->get()
                ->filter(
                    function (
                        WorkflowInstanceStep $step
                    ) use (
                        $user,
                        $employee
                    ): bool {

                        if (
                            !$this->actorMatches(
                                $user,
                                $employee,
                                $step
                            )
                        ) {
                            return false;
                        }


                        $instance =
                            WorkflowInstance::withoutGlobalScopes()
                                ->find(
                                    $step->workflow_instance_id
                                );


                        return
                            $instance !== null
                            &&
                            $instance->subject_type
                            ===
                            InventoryRequest::class
                            &&
                            $instance->subject_id !== null
                            &&
                            (int) $instance->current_step_id
                            ===
                            (int) $step->id;
                    }
                )
                ->values();


        $rows =
            $steps->map(
                function (
                    WorkflowInstanceStep $step
                ): array {

                    $instance =
                        WorkflowInstance::withoutGlobalScopes()
                            ->findOrFail(
                                $step->workflow_instance_id
                            );


                    $inventoryRequest =
                        InventoryRequest::withoutGlobalScopes()
                            ->with([
                                'requesterEmployee',
                                'requesterUser',
                                'site',
                                'department',
                                'targetSite',
                                'targetDepartment',
                                'targetLocation',
                            ])
                            ->findOrFail(
                                $instance->subject_id
                            );


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
                            ->get();


                    return [

                        'step' =>
                            $step,

                        'instance' =>
                            $instance,

                        'request' =>
                            $inventoryRequest,

                        'asset_count' =>
                            $allocations->count(),
                    ];
                }
            );


        return view(
            'final_warehouse_deliveries.index',
            compact(
                'rows'
            )
        );
    }


    public function show(
        Request $request,
        WorkflowInstanceStep $step
    ): View {

        $user =
            $request->user();

        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureCanView(
            $user,
            $employee,
            $step
        );


        $instance =
            WorkflowInstance::withoutGlobalScopes()
                ->findOrFail(
                    $step->workflow_instance_id
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
            abort(404);
        }


        $inventoryRequest =
            InventoryRequest::withoutGlobalScopes()
                ->with([
                    'requesterEmployee',
                    'requesterUser',
                    'site',
                    'department',
                    'targetSite',
                    'targetDepartment',
                    'targetLocation',
                ])
                ->findOrFail(
                    $instance->subject_id
                );


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
                ->orderBy('id')
                ->get();


        $assets =
            Asset::withoutGlobalScopes()
                ->whereIn(
                    'id',
                    $allocations
                        ->pluck('asset_id')
                        ->filter()
                )
                ->with([
                    'category',
                    'assetType',
                ])
                ->get()
                ->keyBy('id');


        $requestItems =
            InventoryRequestItem::query()
                ->whereIn(
                    'id',
                    $allocations
                        ->pluck(
                            'inventory_request_item_id'
                        )
                        ->filter()
                )
                ->get()
                ->keyBy('id');


        $rows =
            $allocations
                ->map(
                    function (
                        InventoryRequestAllocation $allocation
                    ) use (
                        $assets,
                        $requestItems
                    ): array {

                        return [

                            'allocation' =>
                                $allocation,

                            'asset' =>
                                $assets->get(
                                    (int) $allocation->asset_id
                                ),

                            'request_item' =>
                                $requestItems->get(
                                    (int) $allocation
                                        ->inventory_request_item_id
                                ),
                        ];
                    }
                );


        return view(
            'final_warehouse_deliveries.show',
            compact(
                'step',
                'instance',
                'inventoryRequest',
                'rows'
            )
        );
    }


    private function ensureCanView(
        User $user,
        ?Employee $employee,
        WorkflowInstanceStep $step
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
            abort(404);
        }


        $instance =
            WorkflowInstance::withoutGlobalScopes()
                ->findOrFail(
                    $step->workflow_instance_id
                );


        if (
            (int) $instance->current_step_id
            !==
            (int) $step->id
        ) {
            abort(404);
        }


        if (
            !$user->isSuperAdmin()
            &&
            (int) $instance->company_id
            !==
            (int) $user->company_id
        ) {
            abort(404);
        }


        if (
            !$this->actorMatches(
                $user,
                $employee,
                $step
            )
        ) {
            abort(403);
        }
    }


    private function actorMatches(
        User $user,
        ?Employee $employee,
        WorkflowInstanceStep $step
    ): bool {

        if (
            $user->isSuperAdmin()
        ) {
            return true;
        }


        $userMatches =
            $step->resolved_user_id
            !==
            null
            &&
            (int) $step->resolved_user_id
            ===
            (int) $user->id;


        $employeeMatches =
            $employee
            !==
            null
            &&
            $step->resolved_employee_id
            !==
            null
            &&
            (int) $step->resolved_employee_id
            ===
            (int) $employee->id;


        return
            $userMatches
            ||
            $employeeMatches;
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