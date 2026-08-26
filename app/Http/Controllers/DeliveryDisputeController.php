<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeliveryDisputeRequest;
use App\Http\Requests\DeliveryDisputeWarehouseReceiptRequest;
use App\Http\Requests\DeliveryDisputeReplacementAllocationRequest;
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
use App\Services\AssetCustodyService;
use App\Services\InventoryAssetAllocationService;
use App\Services\DeliveryDisputeReplacementReviewService;
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
            'items.replacementAsset.category',
            'items.replacementAllocation',
            'items.requestItem',
            'items.allocation',
            'requesterReceiptStep',
        ]);

        $replacementAvailableAssets =
            collect();

        if (
            $deliveryDispute->status
            ===
            'warehouse_received'
        ) {
            $allocationService =
                app(
                    InventoryAssetAllocationService::class
                );

            $replacementAvailableAssets =
                $allocationService
                    ->availableAssets(
                        (int) $deliveryDispute->company_id
                    )
                    ->with([
                        'category',
                        'assetType',
                    ])
                    ->orderBy('title')
                    ->get();
        }

        return view(
            'delivery_disputes.show',
            compact(
                'deliveryDispute',
                'replacementAvailableAssets'
            )
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

    public function allocateReplacements(
        DeliveryDisputeReplacementAllocationRequest $request,
        DeliveryDispute $deliveryDispute,
        InventoryAssetAllocationService $allocationService,
        DeliveryDisputeReplacementReviewService $reviewService
    ): RedirectResponse {
        $user = $request->user();
        $employee = $this->resolveEmployee($user);

        return DB::transaction(function () use (
            $request,
            $deliveryDispute,
            $allocationService,
            $reviewService,
            $user,
            $employee
        ): RedirectResponse {
            $dispute = DeliveryDispute::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($deliveryDispute->id);

            $this->ensureVisible($user, $dispute);
            $this->ensureWarehouseActor($user, $employee, $dispute);

            if ($dispute->status !== 'warehouse_received') {
                throw ValidationException::withMessages([
                    'dispute' =>
                        'تخصیص جایگزین فقط پس از دریافت فیزیکی کامل مغایرت در انبار امکان‌پذیر است.',
                ]);
            }

            $inventoryRequest = InventoryRequest::withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($dispute->inventory_request_id);

            if ($inventoryRequest->status !== 'replacement_pending') {
                throw ValidationException::withMessages([
                    'request' =>
                        'درخواست در وضعیت انتظار کالای جایگزین نیست.',
                ]);
            }

            $items = DeliveryDisputeItem::withoutGlobalScopes()
                ->with([
                    'requestItem',
                    'asset',
                ])
                ->where('delivery_dispute_id', $dispute->id)
                ->where('status', 'warehouse_received')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'replacements' =>
                        'هیچ قلم دریافت‌شده‌ای برای تخصیص جایگزین وجود ندارد.',
                ]);
            }

            $submitted = collect(
                $request->validated('replacements')
            )
                ->mapWithKeys(
                    fn ($assetId, $itemId): array => [
                        (int) $itemId => (int) $assetId,
                    ]
                );

            $expectedIds = $items
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();

            $submittedIds = $submitted
                ->keys()
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();

            if ($expectedIds->all() !== $submittedIds->all()) {
                throw ValidationException::withMessages([
                    'replacements' =>
                        'برای تمام اقلام برگشتی باید دقیقاً یک دارایی جایگزین انتخاب شود.',
                ]);
            }

            $note = $request->filled('replacement_note')
                ? $request->string('replacement_note')->toString()
                : null;

            $newAllocationIds = [];

            foreach ($items as $item) {
                $requestItem = $item->requestItem;

                if ($requestItem === null) {
                    throw ValidationException::withMessages([
                        'replacements.' . $item->id =>
                            'قلم درخواست مرتبط با مغایرت قابل تشخیص نیست.',
                    ]);
                }

                $replacementAssetId =
                    (int) $submitted->get($item->id);

                if (
                    $item->asset_id !== null
                    &&
                    (int) $item->asset_id === $replacementAssetId
                ) {
                    throw ValidationException::withMessages([
                        'replacements.' . $item->id =>
                            'همان دارایی برگشتی نمی‌تواند به‌عنوان جایگزین انتخاب شود.',
                    ]);
                }

                $replacementAsset = $allocationService
                    ->availableAssets(
                        (int) $dispute->company_id
                    )
                    ->whereKey($replacementAssetId)
                    ->where(
                        'asset_category_id',
                        $requestItem->asset_category_id
                    )
                    ->when(
                        $requestItem->asset_type_id !== null,
                        fn ($query) =>
                            $query->where(
                                'asset_type_id',
                                $requestItem->asset_type_id
                            )
                    )
                    ->lockForUpdate()
                    ->first();

                if ($replacementAsset === null) {
                    throw ValidationException::withMessages([
                        'replacements.' . $item->id =>
                            'دارایی جایگزین باید فعال، موجود در انبار و منطبق با گروه/نوع قلم درخواست باشد.',
                    ]);
                }

                $allocation = $allocationService->reserve(
                    $inventoryRequest,
                    $requestItem,
                    $replacementAsset,
                    $user
                );

                /*
                 * Replacement has already passed manager quantity approval.
                 * It now enters specialist recheck, so allocation is moved to
                 * approved (same state used before specialist review).
                 */
                $allocation->status = 'approved';
                $allocation->approved_at = now();

                $allocation->note = trim(
                    'تخصیص جایگزین بابت مغایرت تحویل'
                    . ($note ? ': ' . $note : '')
                );

                $allocation->save();

                $item->update([
                    'replacement_asset_id' =>
                        $replacementAsset->id,

                    'replacement_allocation_id' =>
                        $allocation->id,

                    'replacement_selected_at' =>
                        now(),

                    'status' =>
                        'replacement_allocated',
                ]);

                $newAllocationIds[] = $allocation->id;
            }

            $dispute->update([
                'status' => 'replacement_allocated',
            ]);

            $inventoryRequest->update([
                'status' => 'replacement_review_pending',
                'fulfilled_at' => null,
            ]);

            $reviewResult = $reviewService->startReview(
                deliveryDispute: $dispute->fresh(),
                inventoryRequest: $inventoryRequest->fresh(),
                actorUser: $user,
            );

            return redirect()
                ->route('delivery-disputes.show', $dispute)
                ->with(
                    'success',
                    $reviewResult['ready_for_redelivery']
                        ? 'کالاهای جایگزین تخصیص یافتند و بدون تأیید تخصصی الزامی، آماده تحویل مجدد هستند.'
                        : 'کالاهای جایگزین تخصیص یافتند و '
                            . $reviewResult['required_branch_count']
                            . ' مسیر تأیید تخصصی الزامی برای بازبینی مجدد فعال شد.'
                );
        });
    }

    public function warehouseReceive(
        DeliveryDisputeWarehouseReceiptRequest $request,
        DeliveryDispute $deliveryDispute,
        AssetCustodyService $custodyService
    ): RedirectResponse {
        $user = $request->user();
        $employee = $this->resolveEmployee($user);

        return DB::transaction(function () use (
            $request,
            $deliveryDispute,
            $custodyService,
            $user,
            $employee
        ): RedirectResponse {
            $dispute = DeliveryDispute::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($deliveryDispute->id);

            $this->ensureVisible($user, $dispute);
            $this->ensureWarehouseActor($user, $employee, $dispute);

            if ($dispute->status !== 'warehouse_pending') {
                throw ValidationException::withMessages([
                    'dispute' => 'این مغایرت دیگر در انتظار دریافت فیزیکی انبار نیست.',
                ]);
            }

            $inventoryRequest = InventoryRequest::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($dispute->inventory_request_id);

            $selected = collect($request->validated('received_items'))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $items = DeliveryDisputeItem::withoutGlobalScopes()
                ->with(['asset', 'allocation'])
                ->where('delivery_dispute_id', $dispute->id)
                ->where('status', 'reported')
                ->whereIn('id', $selected->all())
                ->lockForUpdate()
                ->get();

            if ($items->count() !== $selected->count()) {
                throw ValidationException::withMessages([
                    'received_items' => 'یکی از اقلام انتخاب‌شده معتبر نیست یا قبلاً دریافت شده است.',
                ]);
            }

            $note = $request->filled('warehouse_note')
                ? $request->string('warehouse_note')->toString()
                : null;

            foreach ($items as $item) {
                $asset = $item->asset;
                $allocation = $item->allocation;

                if ($asset === null || $allocation === null) {
                    throw ValidationException::withMessages([
                        'received_items' => 'اطلاعات دارایی یا تخصیص ناقص است.',
                    ]);
                }

                if (
                    (int) $asset->company_id !== (int) $dispute->company_id
                    ||
                    (int) $allocation->company_id !== (int) $dispute->company_id
                    ||
                    (int) $allocation->inventory_request_id !== (int) $inventoryRequest->id
                    ||
                    (int) $allocation->asset_id !== (int) $asset->id
                ) {
                    abort(404);
                }

                if ($allocation->status !== 'delivered') {
                    throw ValidationException::withMessages([
                        'received_items' => 'تخصیص دارایی در وضعیت تحویل‌شده نیست.',
                    ]);
                }

                $custodyService->returnDeliveredAssetToWarehouse(
                    asset: $asset,
                    actorUser: $user,
                    description:
                        'برگشت فیزیکی بابت مغایرت تحویل درخواست '
                        . $inventoryRequest->request_number
                        . ($note ? ' - ' . $note : '')
                );

                $allocation->update([
                    'status' => 'returned',
                    'note' => trim(
                        ($allocation->note ? $allocation->note . PHP_EOL : '')
                        . 'برگشت به انبار بابت مغایرت تحویل'
                        . ($note ? ': ' . $note : '')
                    ),
                ]);

                $item->update([
                    'status' => 'warehouse_received',
                ]);
            }

            $remaining = DeliveryDisputeItem::withoutGlobalScopes()
                ->where('delivery_dispute_id', $dispute->id)
                ->where('status', 'reported')
                ->count();

            if ($remaining === 0) {
                $dispute->update([
                    'status' => 'warehouse_received',
                    'warehouse_received_at' => now(),
                ]);

                $inventoryRequest->update([
                    'status' => 'replacement_pending',
                    'fulfilled_at' => null,
                ]);
            }

            return redirect()
                ->route('delivery-disputes.show', $dispute)
                ->with(
                    'success',
                    $remaining === 0
                        ? 'تمام اقلام در انبار دریافت شدند و درخواست آماده تخصیص کالای جایگزین است.'
                        : 'اقلام انتخاب‌شده دریافت شدند؛ مغایرت تا دریافت سایر اقلام باز می‌ماند.'
                );
        });
    }

    private function ensureWarehouseActor(
        User $user,
        ?Employee $employee,
        DeliveryDispute $dispute
    ): void {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ((int) $user->company_id !== (int) $dispute->company_id) {
            abort(404);
        }

        if ($dispute->workflow_instance_id === null) {
            abort(403);
        }

        $allowed = WorkflowInstanceStep::query()
            ->where('workflow_instance_id', $dispute->workflow_instance_id)
            ->whereIn('code', ['WAREHOUSE', 'FINAL-WAREHOUSE-DELIVERY'])
            ->get()
            ->contains(function (WorkflowInstanceStep $step) use ($user, $employee): bool {
                $userMatches =
                    $step->resolved_user_id !== null
                    &&
                    (int) $step->resolved_user_id === (int) $user->id;

                $employeeMatches =
                    $employee !== null
                    &&
                    $step->resolved_employee_id !== null
                    &&
                    (int) $step->resolved_employee_id === (int) $employee->id;

                return $userMatches || $employeeMatches;
            });

        if (!$allowed) {
            abort(403);
        }
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