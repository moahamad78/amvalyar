<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRepairRequest;
use App\Models\Employee;
use App\Services\AssetRepairLifecycleService;
use App\Services\AssetRepairRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetRepairRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = AssetRepairRequest::withoutGlobalScopes()
            ->with(['asset', 'requesterUser', 'workflowInstance', 'workOrder'])
            ->latest('id');

        if (! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'in:draft,submitted,in_review,approved,in_repair,completed,rejected,cancelled'],
            'priority' => ['nullable', 'in:low,normal,high,critical'],
            'asset_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sla' => ['nullable', 'in:overdue,critical,due_soon'],
        ]);

        $query
            ->when(
                isset($filters['status']),
                fn ($builder) => $builder->where('status', $filters['status'])
            )
            ->when(
                isset($filters['priority']),
                fn ($builder) => $builder->where('priority', $filters['priority'])
            )
            ->when(
                isset($filters['asset_id']),
                function ($builder) use ($filters, $user) {
                    $assetQuery = Asset::withoutGlobalScopes()
                        ->whereKey((int) $filters['asset_id']);

                    if (! $user->isSuperAdmin()) {
                        $assetQuery->where('company_id', $user->company_id);
                    }

                    $assetQuery->firstOrFail();

                    $builder->where('asset_id', (int) $filters['asset_id']);
                }
            )
            ->when(
                isset($filters['from']),
                fn ($builder) => $builder->whereDate('reported_at', '>=', $filters['from'])
            )
            ->when(
                isset($filters['to']),
                fn ($builder) => $builder->whereDate('reported_at', '<=', $filters['to'])
            )
            ->when(
                ($filters['sla'] ?? null) === 'overdue',
                fn ($builder) => $builder
                    ->where('status', AssetRepairRequest::STATUS_IN_REPAIR)
                    ->whereHas('workOrder', fn ($workOrder) => $workOrder
                        ->withoutGlobalScopes()
                        ->where('expected_return_at', '<', now()))
            )
            ->when(
                ($filters['sla'] ?? null) === 'critical',
                fn ($builder) => $builder->where('priority', AssetRepairRequest::PRIORITY_CRITICAL)
            )
            ->when(
                ($filters['sla'] ?? null) === 'due_soon',
                fn ($builder) => $builder
                    ->where('status', AssetRepairRequest::STATUS_IN_REPAIR)
                    ->whereHas('workOrder', fn ($workOrder) => $workOrder
                        ->withoutGlobalScopes()
                        ->whereBetween('expected_return_at', [now(), now()->addDay()]))
            );

        $repairs = $query
            ->paginate(20)
            ->withQueryString();

        $assets = Asset::withoutGlobalScopes()
            ->when(
                ! $user->isSuperAdmin(),
                fn ($builder) => $builder->where('company_id', $user->company_id)
            )
            ->where('is_active', true)
            ->orderBy('title')
            ->orderBy('id')
            ->get(['id', 'title', 'asset_code', 'inventory_code']);

        return view('asset_repairs.index', compact('repairs', 'assets', 'filters'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        if ($user->company_id === null) {
            abort(403);
        }

        $openStatuses = [
            AssetRepairRequest::STATUS_DRAFT,
            AssetRepairRequest::STATUS_SUBMITTED,
            AssetRepairRequest::STATUS_IN_REVIEW,
            AssetRepairRequest::STATUS_APPROVED,
            AssetRepairRequest::STATUS_IN_REPAIR,
        ];

        $assets = Asset::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('status', '!=', 'destroyed')
            ->whereDoesntHave('repairRequests', function ($query) use ($openStatuses): void {
                $query->withoutGlobalScopes()->whereIn('status', $openStatuses);
            })
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        return view('asset_repairs.create', compact('assets'));
    }

    public function store(
        Request $request,
        AssetRepairRequestService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'asset_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'problem_description' => ['required', 'string', 'max:4000'],
            'priority' => ['required', 'in:low,normal,high,critical'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        if ($user->company_id === null) {
            abort(403);
        }

        $asset = Asset::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->whereKey((int) $validated['asset_id'])
            ->firstOrFail();

        $employee = Employee::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        $repair = $service->createDraft(
            asset: $asset,
            requesterUser: $user,
            requesterEmployee: $employee,
            title: (string) $validated['title'],
            problemDescription: (string) $validated['problem_description'],
            priority: (string) $validated['priority'],
            estimatedCost: $validated['estimated_cost'] ?? null
        );

        return redirect()
            ->route('asset-repairs.show', $repair)
            ->with('success', 'درخواست تعمیر به صورت پیش‌نویس ثبت شد.');
    }

    public function show(
        Request $request,
        AssetRepairRequest $assetRepair
    ): View {
        $repair = $this->owned($request, $assetRepair)
            ->load([
                'asset',
                'requesterUser',
                'requesterEmployee',
                'workflowInstance',
                'workOrder.assignedEmployee',
            ]);

        $employees = Employee::withoutGlobalScopes()
            ->where('company_id', $repair->company_id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'personnel_code']);

        return view('asset_repairs.show', compact('repair', 'employees'));
    }

    public function submit(
        Request $request,
        AssetRepairRequest $assetRepair,
        AssetRepairRequestService $service
    ): RedirectResponse {
        $repair = $this->owned($request, $assetRepair);

        if (
            ! $request->user()->isSuperAdmin()
            && (int) $repair->requested_by_user_id !== (int) $request->user()->id
        ) {
            throw ValidationException::withMessages([
                'repair_request' => 'فقط ثبت‌کننده درخواست می‌تواند آن را برای تأیید ارسال کند.',
            ]);
        }

        $service->submit($repair);

        return back()->with('success', 'درخواست تعمیر وارد گردش تأیید شد.');
    }

    public function start(
        Request $request,
        AssetRepairRequest $assetRepair,
        AssetRepairLifecycleService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'repair_type' => ['required', 'in:internal,external'],
            'assigned_employee_id' => ['nullable', 'integer'],
            'external_provider_name' => ['nullable', 'string', 'max:255'],
            'expected_return_at' => ['nullable', 'date'],
            'work_order_notes' => ['nullable', 'string', 'max:6000'],
        ]);

        $repair = $this->owned($request, $assetRepair);
        $employee = null;
        if (isset($validated['assigned_employee_id'])) {
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $repair->company_id)
                ->where('is_active', true)
                ->whereKey((int) $validated['assigned_employee_id'])
                ->firstOrFail();
        }

        $service->startRepair(
            repairRequest: $repair,
            actor: $request->user(),
            repairType: (string) $validated['repair_type'],
            assignedEmployee: $employee,
            externalProviderName: $validated['external_provider_name'] ?? null,
            expectedReturnAt: $request->date('expected_return_at'),
            workOrderNotes: $validated['work_order_notes'] ?? null
        );

        return back()->with('success', 'دستور کار ثبت و عملیات تعمیر شروع شد.');
    }

    public function complete(
        Request $request,
        AssetRepairRequest $assetRepair,
        AssetRepairLifecycleService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'diagnosis' => ['required', 'string', 'max:4000'],
            'repair_notes' => ['required', 'string', 'max:6000'],
            'outcome' => ['required', 'in:repaired,partially_repaired,unrepairable'],
            'labor_cost' => ['required', 'numeric', 'min:0'],
            'parts_cost' => ['required', 'numeric', 'min:0'],
            'external_service_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $repair = $this->owned($request, $assetRepair);

        $service->completeRepairWithCosts(
            repairRequest: $repair,
            actor: $request->user(),
            diagnosis: (string) $validated['diagnosis'],
            repairNotes: (string) $validated['repair_notes'],
            laborCost: $validated['labor_cost'],
            partsCost: $validated['parts_cost'],
            externalServiceCost: $validated['external_service_cost'],
            outcome: (string) $validated['outcome']
        );

        return back()->with('success', 'تعمیر با موفقیت تکمیل شد.');
    }

    public function cancel(Request $request, AssetRepairRequest $assetRepair, AssetRepairLifecycleService $service): RedirectResponse
    {
        $validated = $request->validate(['cancellation_reason' => ['required', 'string', 'max:4000']]);
        $service->cancelRepair($this->owned($request, $assetRepair), $request->user(), $validated['cancellation_reason']);

        return back()->with('success', 'درخواست تعمیر لغو شد.');
    }

    public function reopen(Request $request, AssetRepairRequest $assetRepair, AssetRepairLifecycleService $service): RedirectResponse
    {
        $validated = $request->validate(['reopen_reason' => ['required', 'string', 'max:4000']]);
        $service->reopenRepair($this->owned($request, $assetRepair), $request->user(), $validated['reopen_reason']);

        return back()->with('success', 'درخواست تعمیر دوباره فعال شد.');
    }

    private function owned(
        Request $request,
        AssetRepairRequest $repair
    ): AssetRepairRequest {
        $query = AssetRepairRequest::withoutGlobalScopes()
            ->whereKey($repair->id);

        if (! $request->user()->isSuperAdmin()) {
            $query->where('company_id', $request->user()->company_id);
        }

        return $query->firstOrFail();
    }
}
