<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryDisputeRequest;
use App\Models\DeliveryDispute;
use App\Models\DeliveryDisputeItem;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class DeliveryDisputeController extends Controller
{
    public function index(
        Request $request
    ): View {
        $user = $request->user();

        $query =
            DeliveryDispute::withoutGlobalScopes()
                ->with([
                    'inventoryRequest.requesterEmployee',
                    'inventoryRequest.requesterUser',
                    'items.asset',
                ])
                ->whereIn(
                    'status',
                    [
                        'warehouse_pending',
                        'warehouse_received',
                    ]
                )
                ->orderBy('reported_at')
                ->orderBy('id');

        if (!$user->isSuperAdmin()) {
            $query->where(
                'company_id',
                $user->company_id
            );
        }

        $disputes =
            $query->paginate(25)
                ->withQueryString();

        return view(
            'delivery_disputes.index',
            compact('disputes')
        );
    }

    public function show(
        Request $request,
        DeliveryDispute $deliveryDispute
    ): View {
        $this->ensureVisible(
            $request->user(),
            $deliveryDispute
        );

        $deliveryDispute->load([
            'inventoryRequest.requesterEmployee',
            'inventoryRequest.requesterUser',
            'reportedByUser',
            'reportedByEmployee',
            'items.asset.category',
            'items.requestItem',
            'items.allocation',
            'requesterReceiptStep',
        ]);

        return view(
            'delivery_disputes.show',
            compact('deliveryDispute')
        );
    }

    public function store(
        DeliveryDisputeRequest $request,
        WorkflowInstanceStep $step
    ): RedirectResponse {
        $user =
            $request->user();

        $employee =
            $this->resolveEmployee(
                $user
            );

        return DB::transaction(
            function () use (
                $request,
                $step,
                $user,
                $employee
            ): RedirectResponse {
                $lockedStep =
                    WorkflowInstanceStep::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $step->id
                        );

                if (
                    $lockedStep->code
                    !==
                    'REQUESTER-RECEIPT'
                    ||
                    $lockedStep->status
                    !==
                    'pending'
                ) {
                    throw ValidationException::withMessages([
                        'receipt' =>
                            'اعلام مغایرت فقط در مرحله تأیید دریافت امکان‌پذیر است.',
                    ]);
                }

                $instance =
                    WorkflowInstance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail(
                            $lockedStep->workflow_instance_id
                        );

                if (
                    (int) $instance->current_step_id
                    !==
                    (int) $lockedStep->id
                ) {
                    throw ValidationException::withMessages([
                        'receipt' =>
                            'مرحله تأیید دریافت دیگر مرحله جاری درخواست نیست.',
                    ]);
                }

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
                        ->lockForUpdate()
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

                $this->ensureRequester(
                    user: $user,
                    employee: $employee,
                    step: $lockedStep,
                    inventoryRequest: $inventoryRequest,
                );

                if (
                    $inventoryRequest->status
                    !==
                    'awaiting_receipt'
                ) {
                    throw ValidationException::withMessages([
                        'request' =>
                            'درخواست در وضعیت انتظار تأیید دریافت نیست.',
                    ]);
                }

                $openDispute =
                    DeliveryDispute::withoutGlobalScopes()
                        ->where(
                            'inventory_request_id',
                            $inventoryRequest->id
                        )
                        ->whereIn(
                            'status',
                            [
                                'warehouse_pending',
                                'warehouse_received',
                            ]
                        )
                        ->exists();

                if ($openDispute) {
                    throw ValidationException::withMessages([
                        'request' =>
                            'برای این درخواست یک مغایرت باز وجود دارد.',
                    ]);
                }

                $selectedIds =
                    collect(
                        $request->validated(
                            'dispute_allocations'
                        )
                    )
                        ->map(
                            fn ($id): int =>
                                (int) $id
                        )
                        ->unique()
                        ->values();

                $allocations =
                    InventoryRequestAllocation::query()
                        ->with([
                            'asset',
                        ])
                        ->where(
                            'inventory_request_id',
                            $inventoryRequest->id
                        )
                        ->where(
                            'status',
                            'delivered'
                        )
                        ->whereIn(
                            'id',
                            $selectedIds->all()
                        )
                        ->lockForUpdate()
                        ->get();

                if (
                    $allocations->count()
                    !==
                    $selectedIds->count()
                ) {
                    throw ValidationException::withMessages([
                        'dispute_allocations' =>
                            'یکی از دارایی‌های انتخاب‌شده متعلق به تحویل نهایی این درخواست نیست.',
                    ]);
                }

                foreach ($allocations as $allocation) {
                    if ($allocation->asset === null) {
                        throw ValidationException::withMessages([
                            'dispute_allocations' =>
                                'یکی از تخصیص‌های انتخاب‌شده دارایی معتبر ندارد.',
                        ]);
                    }

                    if (
                        (int) $allocation->asset->company_id
                        !==
                        (int) $inventoryRequest->company_id
                    ) {
                        abort(404);
                    }
                }

                $dispute =
                    new DeliveryDispute([
                        'inventory_request_id' =>
                            $inventoryRequest->id,

                        'workflow_instance_id' =>
                            $instance->id,

                        'requester_receipt_step_id' =>
                            $lockedStep->id,

                        'reported_by_user_id' =>
                            $user->id,

                        'reported_by_employee_id' =>
                            $employee?->id,

                        'status' =>
                            'warehouse_pending',

                        'reason_code' =>
                            $request->string(
                                'reason_code'
                            )->toString(),

                        'description' =>
                            $request->filled(
                                'description'
                            )
                                ? $request->string(
                                    'description'
                                )->toString()
                                : null,

                        'reported_at' =>
                            now(),
                    ]);

                $dispute->company_id =
                    $inventoryRequest->company_id;

                $dispute->save();

                $issueTypes =
                    $request->input(
                        'item_issue_type',
                        []
                    );

                $itemDescriptions =
                    $request->input(
                        'item_description',
                        []
                    );

                foreach ($allocations as $allocation) {
                    $asset =
                        $allocation->asset;

                    $item =
                        new DeliveryDisputeItem([
                            'delivery_dispute_id' =>
                                $dispute->id,

                            'inventory_request_allocation_id' =>
                                $allocation->id,

                            'inventory_request_item_id' =>
                                $allocation
                                    ->inventory_request_item_id,

                            'asset_id' =>
                                $asset->id,

                            'issue_type' =>
                                $issueTypes[
                                    $allocation->id
                                ]
                                ??
                                $dispute->reason_code,

                            'description' =>
                                $itemDescriptions[
                                    $allocation->id
                                ]
                                ??
                                null,

                            'custody_snapshot' => [
                                'custody_type' =>
                                    $asset->custody_type,

                                'custody_user_id' =>
                                    $asset->custody_user_id,

                                'custody_employee_id' =>
                                    $asset->custody_employee_id,

                                'custody_department_id' =>
                                    $asset->custody_department_id,

                                'current_site_id' =>
                                    $asset->current_site_id,

                                'current_location_id' =>
                                    $asset->current_location_id,

                                'status' =>
                                    $asset->status,
                            ],

                            'status' =>
                                'reported',
                        ]);

                    $item->company_id =
                        $inventoryRequest->company_id;

                    $item->save();
                }

                /*
                 * Physical custody deliberately does NOT change here.
                 * Reporting a problem is not the same as physically
                 * returning the asset to warehouse.
                 */
                $inventoryRequest->update([
                    'status' =>
                        'delivery_dispute',

                    'fulfilled_at' =>
                        null,
                ]);

                return redirect()
                    ->route(
                        'approvals.show',
                        $lockedStep
                    )
                    ->with(
                        'success',
                        'مغایرت تحویل ثبت شد و برای بررسی انبار ارسال شد. تا زمان دریافت فیزیکی توسط انبار، دارایی همچنان در تحویل فعلی باقی می‌ماند.'
                    );
            }
        );
    }

    private function ensureRequester(
        User $user,
        ?Employee $employee,
        WorkflowInstanceStep $step,
        InventoryRequest $inventoryRequest
    ): void {
        if ($user->isSuperAdmin()) {
            return;
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

        $requesterUserMatches =
            $inventoryRequest->requester_user_id !== null
            &&
            (int) $inventoryRequest->requester_user_id
            ===
            (int) $user->id;

        $requesterEmployeeMatches =
            $employee !== null
            &&
            $inventoryRequest->requester_employee_id !== null
            &&
            (int) $inventoryRequest->requester_employee_id
            ===
            (int) $employee->id;

        if (
            !(
                ($userMatches || $employeeMatches)
                &&
                (
                    $requesterUserMatches
                    ||
                    $requesterEmployeeMatches
                )
            )
        ) {
            abort(403);
        }
    }

    private function ensureVisible(
        User $user,
        DeliveryDispute $deliveryDispute
    ): void {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (
            (int) $deliveryDispute->company_id
            !==
            (int) $user->company_id
        ) {
            abort(404);
        }
    }

    private function resolveEmployee(
        User $user
    ): ?Employee {
        if ($user->company_id === null) {
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