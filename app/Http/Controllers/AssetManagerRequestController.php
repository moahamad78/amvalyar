<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\AssetCompletenessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AssetManagerRequestController extends Controller
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
                    'ASSET-MANAGER'
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
                            $instance
                            !==
                            null
                            &&
                            $instance->subject_type
                            ===
                            InventoryRequest::class
                            &&
                            $instance->subject_id
                            !==
                            null
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
                                'site',
                                'department',
                            ])
                            ->findOrFail(
                                $instance->subject_id
                            );


                    $allocationCount =
                        InventoryRequestAllocation::query()
                            ->where(
                                'inventory_request_id',
                                $inventoryRequest->id
                            )
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count();


                    return [

                        'step' =>
                            $step,

                        'instance' =>
                            $instance,

                        'request' =>
                            $inventoryRequest,

                        'allocation_count' =>
                            $allocationCount,
                    ];
                }
            );


        return view(
            'asset_manager_requests.index',
            compact(
                'rows'
            )
        );
    }


    public function show(
        Request $request,
        WorkflowInstanceStep $step,
        AssetCompletenessService $completenessService
    ): View {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureStepIsActionable(
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
                    'items',
                ])
                ->findOrFail(
                    $instance->subject_id
                );


        if (
            (int) $inventoryRequest->company_id
            !==
            (int) $instance->company_id
        ) {

            abort(404);
        }


        $allocations =
            InventoryRequestAllocation::query()
                ->with([
                    'asset.category',
                    'asset.assetType',
                ])
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


        $assetIds =
            $allocations
                ->pluck('asset_id')
                ->filter()
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->unique()
                ->values();


        $assets =
            Asset::withoutGlobalScopes()
                ->whereIn(
                    'id',
                    $assetIds
                )
                ->where(
                    'company_id',
                    $inventoryRequest->company_id
                )
                ->with([
                    'category',
                    'assetType',
                    'photos',
                    'attributeValues.definition',
                    'attributeValues.option',
                ])
                ->get()
                ->keyBy('id');


        $rows =
            $allocations
                ->map(
                    function (
                        InventoryRequestAllocation $allocation
                    ) use (
                        $assets,
                        $completenessService
                    ): array {

                        $asset =
                            $assets->get(
                                (int) $allocation->asset_id
                            );


                        if (
                            $asset
                            ===
                            null
                        ) {

                            return [

                                'allocation' =>
                                    $allocation,

                                'asset' =>
                                    null,

                                'completeness' =>
                                    null,
                            ];
                        }


                        return [

                            'allocation' =>
                                $allocation,

                            'asset' =>
                                $asset,

                            'completeness' =>
                                $completenessService->check(
                                    $asset,
                                    AssetCompletenessService::CONTEXT_ASSET_MANAGER_REVIEW
                                ),
                        ];
                    }
                );


        return view(
            'asset_manager_requests.show',
            compact(
                'step',
                'instance',
                'inventoryRequest',
                'rows'
            )
        );
    }


    private function ensureStepIsActionable(
        User $user,
        ?Employee $employee,
        WorkflowInstanceStep $step
    ): void {

        if (
            $step->code
            !==
            'ASSET-MANAGER'
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


        return
            (
                $step->resolved_user_id
                !==
                null
                &&
                (int) $step->resolved_user_id
                ===
                (int) $user->id
            )
            ||
            (
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
                (int) $employee->id
            );
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