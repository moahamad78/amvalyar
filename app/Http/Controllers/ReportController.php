<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransaction;
use App\Models\Company;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless(
            $user->isSuperAdmin() || $user->hasPermission('reports.view'),
            403
        );

        $filterService = app(\App\Services\AssetReportFilters::class);
        $filters = $filterService->validate($request);
        $request->validate([
            'group' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Services\AssetAnalytics::GROUPS))],
            'metric' => ['nullable', 'in:count,value'],
            'kind' => ['nullable', 'in:bar,donut,table'],
        ]);
        $companyId = $user->isSuperAdmin() && $request->filled('company_id')
            ? (int) $request->input('company_id')
            : null;

        $assetsQuery = $user->isSuperAdmin()
            ? Asset::withoutGlobalScopes()
            : Asset::query();

        if ($companyId !== null) {
            $assetsQuery->where('company_id', $companyId);
        }

        $filterService->apply($assetsQuery, $filters);

        $stats = [
            'total' => (clone $assetsQuery)->count(),
            'warehouse' => (clone $assetsQuery)->where('status', 'warehouse')->count(),
            'assigned' => (clone $assetsQuery)->where('status', 'assigned')->count(),
            'destroyed' => (clone $assetsQuery)->where('status', 'destroyed')->count(),
            'purchase_value' => (float) (clone $assetsQuery)->sum('purchase_price'),
        ];

        $assets = (clone $assetsQuery)
            ->with(['category', 'custodyEmployee', 'custodyDepartment', 'currentSite'])
            ->orderBy($filters['sort'] ?? 'id', $filters['direction'] ?? 'desc')->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'assets_page')
            ->withQueryString();

        $transactionsQuery = $user->isSuperAdmin()
            ? AssetTransaction::withoutGlobalScopes()
            : AssetTransaction::query();

        if ($companyId !== null) {
            $transactionsQuery->where('company_id', $companyId);
        }

        $filterService->transactions($transactionsQuery, $filters);

        $transactionStats = [
            'total' => (clone $transactionsQuery)->count(),
            'delivery' => (clone $transactionsQuery)->where('type', 'delivery')->count(),
            'transfer' => (clone $transactionsQuery)->where('type', 'transfer')->count(),
            'return' => (clone $transactionsQuery)->where('type', 'return')->count(),
            'destroy' => (clone $transactionsQuery)->where('type', 'destroy')->count(),
        ];

        $transactions = (clone $transactionsQuery)
            ->with(['asset', 'fromUser', 'toUser', 'creator'])
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'transactions_page')
            ->withQueryString();

        $chart = app(\App\Services\AssetAnalytics::class)->chart([
            'title' => 'توزیع اموال',
            'group' => $request->input('group') ?: 'category',
            'metric' => $request->input('metric') ?: 'count',
            'kind' => $request->input('kind') ?: 'bar',
        ], $filters);

        return view('reports.index', [
            'stats' => $stats,
            'transactionStats' => $transactionStats,
            'assets' => $assets,
            'transactions' => $transactions,
            ...$filterService->options($request),
            'chart' => $chart,
            'selectedCompanyId' => $companyId,
            'currentUser' => $user,
        ]);
    }

    public function printReport(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->isSuperAdmin() || $user->hasPermission('reports.view'), 403);

        $filterService = app(\App\Services\AssetReportFilters::class);
        $filters = $filterService->validate($request);
        $query = $user->isSuperAdmin() ? Asset::withoutGlobalScopes() : Asset::query();
        if ($user->isSuperAdmin() && $request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }
        $filterService->apply($query, $filters);
        $assets = $query->with(['category', 'custodyEmployee', 'custodyDepartment', 'currentSite', 'currentLocation'])
            ->orderBy($filters['sort'] ?? 'id', $filters['direction'] ?? 'desc')
            ->limit(500)
            ->get();

        return view('reports.print', ['assets' => $assets, 'filters' => $filters, 'generatedAt' => now()]);
    }

}
