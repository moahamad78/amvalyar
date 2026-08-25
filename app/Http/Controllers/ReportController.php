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

        $companyId = $user->isSuperAdmin() && $request->filled('company_id')
            ? (int) $request->input('company_id')
            : null;

        $assetsQuery = $user->isSuperAdmin()
            ? Asset::withoutGlobalScopes()
            : Asset::query();

        if ($companyId !== null) {
            $assetsQuery->where('company_id', $companyId);
        }

        $this->applyAssetFilters($assetsQuery, $request);

        $stats = [
            'total' => (clone $assetsQuery)->count(),
            'warehouse' => (clone $assetsQuery)->where('status', 'warehouse')->count(),
            'assigned' => (clone $assetsQuery)->where('status', 'assigned')->count(),
            'destroyed' => (clone $assetsQuery)->where('status', 'destroyed')->count(),
            'purchase_value' => (float) (clone $assetsQuery)->sum('purchase_price'),
        ];

        $assets = (clone $assetsQuery)
            ->with('category')
            ->latest('id')
            ->paginate(20, ['*'], 'assets_page')
            ->withQueryString();

        $transactionsQuery = $user->isSuperAdmin()
            ? AssetTransaction::withoutGlobalScopes()
            : AssetTransaction::query();

        if ($companyId !== null) {
            $transactionsQuery->where('company_id', $companyId);
        }

        $this->applyTransactionFilters($transactionsQuery, $request);
        $this->applyTransactionAssetFilters($transactionsQuery, $request);

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
            ->paginate(20, ['*'], 'transactions_page')
            ->withQueryString();

        $categories = AssetCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $companies = $user->isSuperAdmin()
            ? Company::query()->orderBy('name')->get()
            : collect();

        return view('reports.index', [
            'stats' => $stats,
            'transactionStats' => $transactionStats,
            'assets' => $assets,
            'transactions' => $transactions,
            'categories' => $categories,
            'companies' => $companies,
            'selectedCompanyId' => $companyId,
            'currentUser' => $user,
        ]);
    }

    private function applyAssetFilters(Builder $query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where(
                'asset_category_id',
                (int) $request->input('category_id')
            );
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")
                    ->orWhere('inventory_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%");
            });
        }
    }

    private function applyTransactionFilters(
        Builder $query,
        Request $request
    ): void {
        if ($request->filled('transaction_type')) {
            $query->where(
                'type',
                (string) $request->input('transaction_type')
            );
        }

        if ($request->filled('date_from')) {
            $date = JalaliDate::toGregorianDate(
                (string) $request->input('date_from')
            );

            if ($date !== null) {
                $query->whereDate('created_at', '>=', $date);
            }
        }

        if ($request->filled('date_to')) {
            $date = JalaliDate::toGregorianDate(
                (string) $request->input('date_to')
            );

            if ($date !== null) {
                $query->whereDate('created_at', '<=', $date);
            }
        }
    }

    private function applyTransactionAssetFilters(
        Builder $query,
        Request $request
    ): void {
        if (
            !$request->filled('status')
            && !$request->filled('category_id')
            && !$request->filled('search')
        ) {
            return;
        }

        $query->whereHas('asset', function (Builder $assetQuery) use ($request): void {
            $this->applyAssetFilters($assetQuery, $request);
        });
    }
}