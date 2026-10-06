<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTransaction;
use App\Models\Company;
use App\Models\User;
use App\Services\RoleAccessService;
use App\Services\RoleDashboardService;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        abort_if(
            $user === null,
            401
        );

        if ($user->isSuperAdmin()) {
            return $this->superAdminDashboard();
        }

        return $this->companyDashboard(
            $user
        );
    }

    private function superAdminDashboard(): View
    {
        $today = now()->startOfDay();

        $expiryLimit = now()
            ->addDays(30)
            ->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Main statistics
        |--------------------------------------------------------------------------
        */

        $companyCount =
            Company::query()->count();

        $userCount =
            User::query()
                ->where(
                    'is_super_admin',
                    false
                )
                ->count();

        $assetCount =
            Asset::withoutGlobalScopes()
                ->count();

        $transactionCount =
            AssetTransaction::withoutGlobalScopes()
                ->count();

        $stats = [

            'companies' => $companyCount,

            'active_companies' => Company::query()
                ->where(
                    'status',
                    'active'
                )
                ->count(),

            'demo_companies' => Company::query()
                ->where(
                    'status',
                    'demo'
                )
                ->count(),

            'suspended_companies' => Company::query()
                ->where(
                    'status',
                    'suspended'
                )
                ->count(),

            'expired_companies' => Company::query()
                ->where(
                    'status',
                    'expired'
                )
                ->count(),

            'overdue_companies' => Company::query()
                ->whereNotNull(
                    'license_end'
                )
                ->whereDate(
                    'license_end',
                    '<',
                    $today->toDateString()
                )
                ->count(),

            'users' => $userCount,

            'assets' => $assetCount,

            'warehouse_assets' => Asset::withoutGlobalScopes()
                ->where(
                    'status',
                    'warehouse'
                )
                ->count(),

            'assigned_assets' => Asset::withoutGlobalScopes()
                ->where(
                    'status',
                    'assigned'
                )
                ->count(),

            'organizational_assets' => Asset::withoutGlobalScopes()
                ->where(
                    'custody_type',
                    'organization'
                )
                ->count(),

            'destroyed_assets' => Asset::withoutGlobalScopes()
                ->where(
                    'status',
                    'destroyed'
                )
                ->count(),

            'transactions' => $transactionCount,

            'purchase_value' => (float) Asset::withoutGlobalScopes()
                ->sum(
                    'purchase_price'
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Expiring companies
        |--------------------------------------------------------------------------
        */

        $expiringCompanies =
            Company::query()
                ->whereIn(
                    'status',
                    [
                        'active',
                        'demo',
                    ]
                )
                ->whereNotNull(
                    'license_end'
                )
                ->whereDate(
                    'license_end',
                    '>=',
                    $today->toDateString()
                )
                ->whereDate(
                    'license_end',
                    '<=',
                    $expiryLimit->toDateString()
                )
                ->orderBy(
                    'license_end'
                )
                ->limit(10)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Recent companies
        |--------------------------------------------------------------------------
        */

        $recentCompanies =
            Company::query()
                ->latest('id')
                ->limit(8)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Recent asset transactions
        |--------------------------------------------------------------------------
        */

        $recentTransactions =
            AssetTransaction::withoutGlobalScopes()
                ->with([
                    'asset',
                    'fromUser',
                    'toUser',
                    'creator',
                ])
                ->latest('id')
                ->limit(10)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Company usage overview
        |--------------------------------------------------------------------------
        */

        $companyUsage =
            Company::query()
                ->withCount([
                    'assets',
                    'users as tenant_users_count' => fn ($query) => $query->where('is_super_admin', false),
                ])
                ->get()
                ->map(
                    function (Company $company): array {
                        $assets = (int) $company->assets_count;
                        $users = (int) $company->tenant_users_count;

                        return [
                            'id' => $company->id,

                            'name' => $company->name,

                            'code' => $company->code,

                            'status' => $company->status,

                            'assets' => $assets,

                            'users' => $users,

                            'max_assets' => (int) $company->max_assets,

                            'max_users' => (int) $company->max_users,

                            'asset_percent' => $company->max_assets > 0
                                    ? min(
                                        100,
                                        round(
                                            (
                                                $assets
                                                /
                                                $company->max_assets
                                            ) * 100,
                                            1
                                        )
                                    )
                                    : 0,

                            'user_percent' => $company->max_users > 0
                                    ? min(
                                        100,
                                        round(
                                            (
                                                $users
                                                /
                                                $company->max_users
                                            ) * 100,
                                            1
                                        )
                                    )
                                    : 0,
                        ];
                    }
                )
                ->sortByDesc(
                    'asset_percent'
                )
                ->values();

        return view(
            'dashboard.super_admin',
            compact(
                'stats',
                'expiringCompanies',
                'recentCompanies',
                'recentTransactions',
                'companyUsage'
            )
        );
    }

    private function companyDashboard(
        User $user
    ): View {
        $company = $user->company;

        abort_if(
            $company === null,
            403,
            'کاربر به هیچ شرکتی متصل نیست.'
        );

        $access = app(RoleAccessService::class);
        if ($access->scope($user) !== 'company') {
            $assets = Asset::query()->with(['category', 'currentSite', 'currentLocation'])->latest('id')->paginate(15);
            $roleDashboard = app(RoleDashboardService::class)->forUser($user);

            return view('dashboard.personal', compact('company', 'assets', 'roleDashboard'));
        }

        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        $canViewFinancial = $access->financial($user);

        $assetSummaryQuery = Asset::query()
            ->selectRaw('COUNT(*) AS assets')
            ->selectRaw("SUM(CASE WHEN status = 'warehouse' THEN 1 ELSE 0 END) AS warehouse_assets")
            ->selectRaw("SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS assigned_assets")
            ->selectRaw("SUM(CASE WHEN custody_type = 'organization' THEN 1 ELSE 0 END) AS organizational_assets")
            ->selectRaw("SUM(CASE WHEN status = 'destroyed' THEN 1 ELSE 0 END) AS destroyed_assets");

        if ($canViewFinancial) {
            $assetSummaryQuery->selectRaw('COALESCE(SUM(purchase_price), 0) AS purchase_value');
        }

        $assetSummary = $assetSummaryQuery->firstOrFail();
        $assetCount = (int) $assetSummary->assets;

        $userCount =
            User::query()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'is_super_admin',
                    false
                )
                ->count();

        $stats = [

            'assets' => $assetCount,

            'warehouse_assets' => (int) $assetSummary->warehouse_assets,

            'assigned_assets' => (int) $assetSummary->assigned_assets,

            'organizational_assets' => (int) $assetSummary->organizational_assets,

            'destroyed_assets' => (int) $assetSummary->destroyed_assets,

            'users' => $userCount,

            'transactions' => AssetTransaction::query()
                ->count(),

            'purchase_value' => $canViewFinancial
                    ? (float) $assetSummary->purchase_value
                    : null,
        ];

        /*
        |--------------------------------------------------------------------------
        | Capacity
        |--------------------------------------------------------------------------
        */

        $assetCapacityPercent =
            $company->max_assets > 0
                ? min(
                    100,
                    round(
                        (
                            $assetCount
                            /
                            $company->max_assets
                        ) * 100,
                        1
                    )
                )
                : 0;

        $userCapacityPercent =
            $company->max_users > 0
                ? min(
                    100,
                    round(
                        (
                            $userCount
                            /
                            $company->max_users
                        ) * 100,
                        1
                    )
                )
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Recent Assets
        |--------------------------------------------------------------------------
        */

        $recentAssets =
            Asset::query()
                ->with(
                    'category'
                )
                ->latest('id')
                ->limit(8)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Recent Transactions
        |--------------------------------------------------------------------------
        */

        $recentTransactions =
            AssetTransaction::query()
                ->with([
                    'asset',
                    'fromUser',
                    'toUser',
                    'creator',
                ])
                ->latest('id')
                ->limit(8)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | Category Breakdown
        |--------------------------------------------------------------------------
        */

        $categoryCounts =
            Asset::query()
                ->selectRaw(
                    'asset_category_id, COUNT(*) as total'
                )
                ->groupBy(
                    'asset_category_id'
                )
                ->orderByDesc(
                    'total'
                )
                ->get();

        $categoryIds =
            $categoryCounts
                ->pluck(
                    'asset_category_id'
                )
                ->filter()
                ->values();

        $categoryNames =
            AssetCategory::query()
                ->whereIn(
                    'id',
                    $categoryIds
                )
                ->pluck(
                    'name',
                    'id'
                );

        $categoryStats =
            $categoryCounts
                ->map(
                    function ($item) use ($categoryNames) {

                        return [
                            'name' => $categoryNames[
                                    $item->asset_category_id
                                ] ?? 'بدون دسته‌بندی',

                            'total' => (int) $item->total,
                        ];
                    }
                );

        $roleDashboard =
            app(
                RoleDashboardService::class
            )->forUser(
                $user
            );

        return view(
            'dashboard.company',
            compact(
                'company',
                'stats',
                'recentAssets',
                'recentTransactions',
                'categoryStats',
                'assetCapacityPercent',
                'userCapacityPercent',
                'roleDashboard'
            )
        );
    }
}
