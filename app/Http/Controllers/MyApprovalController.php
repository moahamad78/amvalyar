<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkflowActionRequest;
use App\Http\Requests\WarehouseAllocationRequest;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\InventoryAssetAllocationService;
use App\Services\WorkflowRuntimeService;
use App\Services\WarehouseSpecialistGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class MyApprovalController extends Controller
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


        $query =
            WorkflowInstanceStep::query()
                ->with([
                    'instance',
                    'instance.requesterEmployee',
                    'resolvedEmployee',
                    'resolvedUser',
                ])
                ->where(
                    'status',
                    'pending'
                );


        /*
        |--------------------------------------------------------------------------
        | Tenant
        |--------------------------------------------------------------------------
        */

        if (
            !$user->isSuperAdmin()
        ) {

            $query->whereHas(
                'instance',
                function ($query) use ($user): void {

                    $query->where(
                        'company_id',
                        $user->company_id
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | My Approvals Only
        |--------------------------------------------------------------------------
        */

        if (
            $employee !== null
        ) {

            $query->where(
                function ($query) use (
                    $user,
                    $employee
                ): void {

                    $query
                        ->where(
                            'resolved_user_id',
                            $user->id
                        )
                        ->orWhere(
                            'resolved_employee_id',
                            $employee->id
                        );
                }
            );

        } else {

            $query->where(
                'resolved_user_id',
                $user->id
            );
        }


        $steps =
            $query
                ->orderBy('due_at')
                ->orderBy('activated_at')
                ->paginate(20);


        return view(
            'approvals.index',
            compact(
                'steps',
                'employee'
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


        $this->ensureCanAct(
            $user,
            $employee,
            $step
        );


        $step->load([
            'instance',
            'instance.requesterEmployee',
            'instance.requesterUser',
            'instance.actions',
            'instance.steps',
            'resolvedEmployee',
            'resolvedUser',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Subject
        |--------------------------------------------------------------------------
        */

        $inventoryRequest =
            null;


        if (
            $step->instance->subject_type
            ===
            InventoryRequest::class
            &&
            $step->instance->subject_id !== null
        ) {

            $inventoryRequest =
                InventoryRequest::withoutGlobalScopes()
                    ->with([
                        'items',
                        'site',
                        'department',
                        'requesterEmployee',
                        'requesterUser',
                    ])
                    ->findOrFail(
                        $step->instance->subject_id
                    );


            if (
                (int) $inventoryRequest->company_id
                !==
                (int) $step->instance->company_id
            ) {

                abort(404);
            }
        }


        $canEditItemDecision =
            $inventoryRequest !== null
            &&
            $step->code === 'DIRECT-MANAGER'
            &&
            $step->status === 'pending';


        /*
        |--------------------------------------------------------------------------
        | Warehouse Allocation
        |--------------------------------------------------------------------------
        */

        $warehouseAllocationMode =
            $inventoryRequest !== null
            &&
            $step->code === 'WAREHOUSE'
            &&
            $step->status === 'pending';


        $warehouseAvailableAssets =
            collect();


        $warehouseAllocations =
            collect();


        if ($warehouseAllocationMode) {

            $allocationService =
                app(
                    InventoryAssetAllocationService::class
                );


            $warehouseAvailableAssets =
                $allocationService
                    ->availableAssets(
                        (int) $inventoryRequest->company_id
                    )
                    ->with('category')
                    ->get();


            $warehouseAllocations =
                InventoryRequestAllocation::query()
                    ->with([
                        'asset.category',
                    ])
                    ->where(
                        'inventory_request_id',
                        $inventoryRequest->id
                    )
                    ->whereIn(
                        'status',
                        [
                            'reserved',
                            'approved',
                        ]
                    )
                    ->get()
                    ->groupBy(
                        'inventory_request_item_id'
                    );
        }


        /*
        |--------------------------------------------------------------------------
        | Requester Receipt / Delivery Dispute
        |--------------------------------------------------------------------------
        */

        $requesterReceiptAllocations =
            collect();

        if (
            $inventoryRequest !== null
            &&
            $step->code === 'REQUESTER-RECEIPT'
            &&
            $step->status === 'pending'
        ) {
            $requesterReceiptAllocations =
                InventoryRequestAllocation::query()
                    ->with([
                        'asset.category',
                        'requestItem',
                    ])
                    ->where(
                        'inventory_request_id',
                        $inventoryRequest->id
                    )
                    ->where(
                        'status',
                        'delivered'
                    )
                    ->orderBy('id')
                    ->get();
        }


        return view(
            'approvals.show',
            compact(
                'step',
                'employee',
                'inventoryRequest',
                'canEditItemDecision',
                'warehouseAllocationMode',
                'warehouseAvailableAssets',
                'warehouseAllocations',
                'requesterReceiptAllocations'
            )
        );
    }


    public function act(
        WorkflowActionRequest $request,
        WorkflowInstanceStep $step,
        WorkflowRuntimeService $runtimeService
    ): RedirectResponse {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureCanAct(
            $user,
            $employee,
            $step
        );


        $instance =
            WorkflowInstance::withoutGlobalScopes()
                ->findOrFail(
                    $step->workflow_instance_id
                );


        $action =
            $request->string(
                'action'
            )->toString();


        $comment =
            $request->filled('comment')
                ? $request->string(
                    'comment'
                )->toString()
                : null;


        return DB::transaction(
            function () use (
                $request,
                $step,
                $instance,
                $runtimeService,
                $employee,
                $user,
                $action,
                $comment
            ): RedirectResponse {

                /*
                |--------------------------------------------------------------------------
                | Inventory Request Item Decision
                |--------------------------------------------------------------------------
                |
                | فقط مدیر مستقیم هنگام Approve می‌تواند مقدار تأییدشده
                | اقلام را مشخص کند.
                |
                */

                if (
                    $action === 'approve'
                    &&
                    $step->code === 'DIRECT-MANAGER'
                    &&
                    $instance->subject_type
                    ===
                    InventoryRequest::class
                    &&
                    $instance->subject_id !== null
                ) {

                    $inventoryRequest =
                        InventoryRequest::withoutGlobalScopes()
                            ->with('items')
                            ->lockForUpdate()
                            ->findOrFail(
                                $instance->subject_id
                            );


                    if (
                        (int) $inventoryRequest->company_id
                        !==
                        (int) $instance->company_id
                    ) {

                        throw ValidationException::withMessages([
                            'request' =>
                                'درخواست کالا متعلق به شرکت این گردش کاری نیست.',
                        ]);
                    }


                    $submittedItems =
                        $request->input(
                            'items',
                            []
                        );


                    $positiveApprovedItems =
                        0;


                    foreach (
                        $inventoryRequest->items
                        as $item
                    ) {

                        $submitted =
                            $submittedItems[
                                $item->id
                            ]
                            ?? null;


                        if (
                            $submitted === null
                            ||
                            !array_key_exists(
                                'approved_quantity',
                                $submitted
                            )
                        ) {

                            throw ValidationException::withMessages([
                                'items' =>
                                    'تعداد تأییدشده تمام اقلام باید مشخص شود.',
                            ]);
                        }


                        $approvedQuantity =
                            (float) $submitted[
                                'approved_quantity'
                            ];


                        $requestedQuantity =
                            (float) $item
                                ->requested_quantity;


                        if (
                            $approvedQuantity < 0
                            ||
                            $approvedQuantity
                            >
                            $requestedQuantity
                        ) {

                            throw ValidationException::withMessages([
                                'items.' .
                                $item->id .
                                '.approved_quantity' =>
                                    'تعداد تأییدشده «' .
                                    $item->item_name .
                                    '» باید بین صفر و تعداد درخواستی باشد.',
                            ]);
                        }


                        if (
                            $approvedQuantity > 0
                        ) {

                            $positiveApprovedItems++;
                        }


                        $item->update([
                            'approved_quantity' =>
                                $approvedQuantity,

                            'status' =>
                                $approvedQuantity > 0
                                    ? 'approved'
                                    : 'rejected',

                            'decision_note' =>
                                isset(
                                    $submitted[
                                        'decision_note'
                                    ]
                                )
                                    ? trim(
                                        (string) $submitted[
                                            'decision_note'
                                        ]
                                    )
                                    : null,
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | At least one approved item
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $positiveApprovedItems
                        ===
                        0
                    ) {

                        throw ValidationException::withMessages([
                            'items' =>
                                'حداقل یک قلم باید دارای تعداد تأییدشده بیشتر از صفر باشد. در غیر این صورت کل درخواست را رد کنید.',
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Approve
                |--------------------------------------------------------------------------
                */

                if (
                    $action === 'approve'
                ) {

                    $runtimeService->approve(
                        instance:
                            $instance,

                        actorEmployee:
                            $employee,

                        actorUser:
                            $user,

                        comment:
                            $comment
                    );


                    return redirect()
                        ->route(
                            'approvals.index'
                        )
                        ->with(
                            'success',
                            'مرحله با موفقیت تأیید شد.'
                        );
                }


                /*
                |--------------------------------------------------------------------------
                | Reject
                |--------------------------------------------------------------------------
                */

                $runtimeService->reject(
                    instance:
                        $instance,

                    actorEmployee:
                        $employee,

                    actorUser:
                        $user,

                    comment:
                        $comment
                );


                return redirect()
                    ->route(
                        'approvals.index'
                    )
                    ->with(
                        'success',
                        'مرحله رد شد.'
                    );
            }
        );
    }


    public function saveWarehouseAllocations(
        WarehouseAllocationRequest $request,
        WorkflowInstanceStep $step,
        InventoryAssetAllocationService $allocationService
    ): RedirectResponse {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureCanAct(
            $user,
            $employee,
            $step
        );


        if (
            $step->code
            !==
            'WAREHOUSE'
        ) {

            abort(404);
        }


        $step->loadMissing(
            'instance'
        );


        $instance =
            $step->instance;


        if (
            $instance->subject_type
            !==
            InventoryRequest::class
            ||
            $instance->subject_id === null
        ) {

            throw ValidationException::withMessages([
                'request' =>
                    'موضوع این مرحله یک درخواست کالا نیست.',
            ]);
        }


        return DB::transaction(
            function () use (
                $request,
                $instance,
                $user,
                $allocationService
            ): RedirectResponse {

                $inventoryRequest =
                    InventoryRequest::withoutGlobalScopes()
                        ->with('items')
                        ->lockForUpdate()
                        ->findOrFail(
                            $instance->subject_id
                        );


                if (
                    (int) $inventoryRequest->company_id
                    !==
                    (int) $instance->company_id
                ) {

                    throw ValidationException::withMessages([
                        'request' =>
                            'شرکت درخواست و گردش کاری مطابقت ندارد.',
                    ]);
                }


                $submitted =
                    $request->input(
                        'allocations',
                        []
                    );


                $validItemIds =
                    $inventoryRequest->items
                        ->pluck('id')
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->all();


                /*
                |--------------------------------------------------------------------------
                | Reject foreign item IDs
                |--------------------------------------------------------------------------
                */

                foreach (
                    array_keys($submitted)
                    as $submittedItemId
                ) {

                    if (
                        !in_array(
                            (int) $submittedItemId,
                            $validItemIds,
                            true
                        )
                    ) {

                        throw ValidationException::withMessages([
                            'allocations' =>
                                'یکی از اقلام ارسالی متعلق به این درخواست نیست.',
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Build desired pairs
                |--------------------------------------------------------------------------
                */

                $desiredPairs =
                    [];


                $allSelectedAssetIds =
                    [];


                foreach (
                    $inventoryRequest->items
                    as $item
                ) {

                    $selectedIds =
                        collect(
                            $submitted[
                                $item->id
                            ]
                            ?? []
                        )
                            ->map(
                                fn ($id) =>
                                    (int) $id
                            )
                            ->unique()
                            ->values();


                    /*
                    |--------------------------------------------------------------------------
                    | Asset quantities are discrete
                    |--------------------------------------------------------------------------
                    */

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


                    if (
                        $selectedIds->count()
                        >
                        (int) round(
                            $approvedQuantity
                        )
                    ) {

                        throw ValidationException::withMessages([
                            'allocations.' . $item->id =>
                                'تعداد اموال انتخاب‌شده برای «' .
                                $item->item_name .
                                '» بیشتر از تعداد تأییدشده است.',
                        ]);
                    }


                    foreach (
                        $selectedIds
                        as $assetId
                    ) {

                        if (
                            in_array(
                                $assetId,
                                $allSelectedAssetIds,
                                true
                            )
                        ) {

                            throw ValidationException::withMessages([
                                'allocations' =>
                                    'یک دارایی نمی‌تواند همزمان برای دو قلم انتخاب شود.',
                            ]);
                        }


                        $allSelectedAssetIds[] =
                            $assetId;


                        $desiredPairs[
                            $item->id . ':' . $assetId
                        ] = [
                            'item' =>
                                $item,

                            'asset_id' =>
                                $assetId,
                        ];
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Existing active allocations
                |--------------------------------------------------------------------------
                */

                $existing =
                    InventoryRequestAllocation::query()
                        ->where(
                            'inventory_request_id',
                            $inventoryRequest->id
                        )
                        ->whereIn(
                            'status',
                            [
                                'reserved',
                                'approved',
                            ]
                        )
                        ->lockForUpdate()
                        ->get();


                /*
                |--------------------------------------------------------------------------
                | Release removed selections first
                |--------------------------------------------------------------------------
                */

                foreach (
                    $existing
                    as $allocation
                ) {

                    $pairKey =
                        $allocation->inventory_request_item_id
                        . ':'
                        . $allocation->asset_id;


                    if (
                        !array_key_exists(
                            $pairKey,
                            $desiredPairs
                        )
                    ) {

                        $allocationService->release(
                            $allocation
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Refresh currently active pairs
                |--------------------------------------------------------------------------
                */

                $activePairs =
                    InventoryRequestAllocation::query()
                        ->where(
                            'inventory_request_id',
                            $inventoryRequest->id
                        )
                        ->whereIn(
                            'status',
                            [
                                'reserved',
                                'approved',
                            ]
                        )
                        ->get()
                        ->mapWithKeys(
                            fn ($allocation) => [
                                $allocation->inventory_request_item_id
                                . ':'
                                . $allocation->asset_id
                                =>
                                true,
                            ]
                        );


                /*
                |--------------------------------------------------------------------------
                | Reserve new selections
                |--------------------------------------------------------------------------
                */

                foreach (
                    $desiredPairs
                    as $pairKey => $desired
                ) {

                    if (
                        $activePairs->has(
                            $pairKey
                        )
                    ) {
                        continue;
                    }


                    $asset =
                        \App\Models\Asset::withoutGlobalScopes()
                            ->findOrFail(
                                $desired[
                                    'asset_id'
                                ]
                            );


                    $allocationService->reserve(
                        request:
                            $inventoryRequest,

                        item:
                            $desired[
                                'item'
                            ],

                        asset:
                            $asset,

                        actor:
                            $user
                    );
                }


                return back()->with(
                    'success',
                    'انتخاب و رزرو اموال با موفقیت ذخیره شد.'
                );
            }
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


    private function ensureCanAct(
        User $user,
        ?Employee $employee,
        WorkflowInstanceStep $step
    ): void {

        $step->loadMissing(
            'instance'
        );


        if (
            $step->status
            !==
            'pending'
        ) {
            abort(404);
        }


        if (
            !$user->isSuperAdmin()
            &&
            (int) $step->instance->company_id
            !==
            (int) $user->company_id
        ) {
            abort(404);
        }


        $userMatches =
            $step->resolved_user_id !== null
            &&
            (int) $step->resolved_user_id
            ===
            (int) $user->id;


        $employeeMatches =
            $employee !== null
            &&
            $step->resolved_employee_id !== null
            &&
            (int) $step->resolved_employee_id
            ===
            (int) $employee->id;


        if (
            !$userMatches
            &&
            !$employeeMatches
        ) {
            abort(404);
        }
    }

    public function finalizeWarehouseSelection(
        \Illuminate\Http\Request $request,
        WorkflowInstanceStep $step,
        WarehouseSpecialistGateService $gateService
    ): RedirectResponse {

        $user =
            $request->user();


        $employee =
            $this->resolveEmployee(
                $user
            );


        $this->ensureCanAct(
            $user,
            $employee,
            $step
        );


        if (
            $step->code
            !==
            'WAREHOUSE'
        ) {

            abort(404);
        }


        $step->loadMissing(
            'instance'
        );


        $instance =
            $step->instance;


        if (
            $instance->subject_type
            !==
            InventoryRequest::class
            ||
            $instance->subject_id === null
        ) {

            throw ValidationException::withMessages([
                'request' =>
                    'موضوع این مرحله یک درخواست کالا نیست.',
            ]);
        }


        $inventoryRequest =
            InventoryRequest::withoutGlobalScopes()
                ->with('items')
                ->findOrFail(
                    $instance->subject_id
                );


        $result =
            $gateService->finalizeWarehouse(
                instance:
                    $instance,

                inventoryRequest:
                    $inventoryRequest,

                warehouseStep:
                    $step,

                actorEmployee:
                    $employee,

                actorUser:
                    $user,

                comment:
                    $request->filled('comment')
                        ? trim(
                            (string) $request->input(
                                'comment'
                            )
                        )
                        : null
            );


        if (
            $result['branch_count']
            >
            0
        ) {

            return redirect()
                ->route(
                    'approvals.index'
                )
                ->with(
                    'success',
                    'انتخاب اموال نهایی شد و '
                    .
                    $result['branch_count']
                    .
                    ' مسیر تأیید تخصصی به‌صورت موازی فعال شد.'
                );
        }


        return redirect()
            ->route(
                'approvals.index'
            )
            ->with(
                'success',
                'انتخاب اموال نهایی شد و درخواست به مرحله بعد منتقل شد.'
            );
    }

}