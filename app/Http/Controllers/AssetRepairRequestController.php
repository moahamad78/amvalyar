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
            ->with(['asset', 'requesterUser', 'workflowInstance'])
            ->latest('id');

        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $repairs = $query->paginate(20);

        return view('asset_repairs.index', compact('repairs'));
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
            ->load(['asset', 'requesterUser', 'requesterEmployee', 'workflowInstance']);

        return view('asset_repairs.show', compact('repair'));
    }

    public function submit(
        Request $request,
        AssetRepairRequest $assetRepair,
        AssetRepairRequestService $service
    ): RedirectResponse {
        $repair = $this->owned($request, $assetRepair);

        if (
            !$request->user()->isSuperAdmin()
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
        $repair = $this->owned($request, $assetRepair);
        $service->startRepair($repair, $request->user());

        return back()->with('success', 'عملیات تعمیر شروع شد.');
    }

    public function complete(
        Request $request,
        AssetRepairRequest $assetRepair,
        AssetRepairLifecycleService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'diagnosis' => ['required', 'string', 'max:4000'],
            'repair_notes' => ['required', 'string', 'max:6000'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $repair = $this->owned($request, $assetRepair);

        $service->completeRepair(
            repairRequest: $repair,
            actor: $request->user(),
            diagnosis: (string) $validated['diagnosis'],
            repairNotes: (string) $validated['repair_notes'],
            actualCost: $validated['actual_cost'] ?? null
        );

        return back()->with('success', 'تعمیر با موفقیت تکمیل شد.');
    }

    private function owned(
        Request $request,
        AssetRepairRequest $repair
    ): AssetRepairRequest {
        $query = AssetRepairRequest::withoutGlobalScopes()
            ->whereKey($repair->id);

        if (!$request->user()->isSuperAdmin()) {
            $query->where('company_id', $request->user()->company_id);
        }

        return $query->firstOrFail();
    }
}