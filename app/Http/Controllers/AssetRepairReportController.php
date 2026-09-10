<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRepairRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AssetRepairReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $filters = $request->validate([
            'status' => ['nullable', 'in:draft,submitted,in_review,approved,in_repair,completed,rejected,cancelled'],
            'repair_type' => ['nullable', 'in:internal,external'],
            'outcome' => ['nullable', 'in:repaired,partially_repaired,unrepairable'],
            'provider' => ['nullable', 'string', 'max:255'],
            'asset_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);

        $query = AssetRepairRequest::withoutGlobalScopes()
            ->where('company_id', $user->company_id);
        $this->applyFilters($query, $filters);

        $summary = [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->whereIn('status', ['draft', 'submitted', 'in_review', 'approved', 'in_repair'])->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'estimated_cost' => (float) (clone $query)->sum('estimated_cost'),
            'actual_cost' => (float) (clone $query)->sum('actual_cost'),
        ];
        $summary['cost_variance'] = $summary['actual_cost'] - $summary['estimated_cost'];

        $repairs = (clone $query)
            ->with(['asset', 'workOrder.assignedEmployee'])
            ->latest('reported_at')
            ->paginate(20)
            ->withQueryString();

        $completed = (clone $query)->where('status', 'completed')->with(['asset', 'workOrder'])->get();
        $providers = $completed->filter(fn ($repair) => filled($repair->workOrder?->external_provider_name))
            ->groupBy(fn ($repair) => $repair->workOrder->external_provider_name)
            ->map(fn ($items, $name) => [
                'name' => $name,
                'repairs' => $items->count(),
                'cost' => (float) $items->sum('actual_cost'),
            ])->sortByDesc('cost')->values();

        $assetPerformance = $completed->groupBy('asset_id')->map(fn ($items) => [
            'asset' => $items->first()->asset,
            'repairs' => $items->count(),
            'cost' => (float) $items->sum('actual_cost'),
        ])->sortByDesc('cost')->take(10)->values();

        $assets = Asset::withoutGlobalScopes()->where('company_id', $user->company_id)->orderBy('title')->get(['id', 'title', 'asset_code', 'inventory_code']);

        return view('reports.repairs', compact('summary', 'repairs', 'providers', 'assetPerformance', 'assets', 'filters'));
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['asset_id'] ?? null, fn ($q, $value) => $q->where('asset_id', $value))
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->whereDate('reported_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->whereDate('reported_at', '<=', $value))
            ->when($filters['repair_type'] ?? null, fn ($q, $value) => $q->whereHas('workOrder', fn ($wo) => $wo->withoutGlobalScopes()->where('repair_type', $value)))
            ->when($filters['outcome'] ?? null, fn ($q, $value) => $q->whereHas('workOrder', fn ($wo) => $wo->withoutGlobalScopes()->where('outcome', $value)))
            ->when($filters['provider'] ?? null, fn ($q, $value) => $q->whereHas('workOrder', fn ($wo) => $wo->withoutGlobalScopes()->where('external_provider_name', 'like', '%'.trim($value).'%')));
    }
}
