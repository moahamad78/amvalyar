<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Site;
use App\Models\User;
use App\Models\WarehouseSiteAccess;
use App\Services\WarehouseSiteAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WarehouseInventoryController extends Controller
{
    public function index(Request $request, WarehouseSiteAccessService $access): View
    {
        $user = $request->user();
        $filters = $request->validate(['site_id' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100']]);
        $siteIds = $access->siteIds($user, 'view');
        $sites = Site::withoutGlobalScopes()->where('company_id', $user->company_id)->where('is_active', true)
            ->when(! $access->isCompanyWide($user), fn ($q) => $q->whereIn('id', $siteIds))->orderBy('sort_order')->orderBy('name')->get();
        $selectedSiteId = $filters['site_id'] ?? null;
        if ($selectedSiteId !== null && ! $sites->contains('id', (int) $selectedSiteId)) abort(404);
        $base = Asset::query()->where('status', 'warehouse')->where('custody_type', 'warehouse')
            ->when($selectedSiteId, fn ($q) => $q->where('current_site_id', $selectedSiteId))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($search) => $search->where('title', 'like', '%'.$term.'%')->orWhere('asset_code', 'like', '%'.$term.'%')->orWhere('inventory_code', 'like', '%'.$term.'%')->orWhere('serial_number', 'like', '%'.$term.'%')));
        $assets = (clone $base)->with(['category', 'assetType', 'currentSite'])->latest('id')->paginate(30)->withQueryString();
        $summary = ['all' => (clone $base)->count(), 'coded' => (clone $base)->whereNotNull('asset_code')->count(), 'pending_code' => (clone $base)->whereNull('asset_code')->count()];
        return view('warehouse-inventory.index', compact('assets', 'sites', 'summary'));
    }

    public function accessIndex(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $sites = Site::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('sort_order')->get();
        $users = User::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->where('is_super_admin', false)->with('role')->orderBy('name')->get();
        $accesses = WarehouseSiteAccess::query()->where('company_id', $companyId)->get()->keyBy(fn ($access) => $access->site_id.':'.$access->user_id);
        return view('warehouse-inventory.accesses', compact('sites', 'users', 'accesses'));
    }

    public function updateAccess(Request $request, Site $site): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless((int) $site->company_id === $companyId && $site->is_active, 404);
        $data = $request->validate(['accesses' => ['nullable', 'array'], 'accesses.*.view' => ['nullable', 'boolean'], 'accesses.*.issue' => ['nullable', 'boolean'], 'accesses.*.receive' => ['nullable', 'boolean'], 'accesses.*.transfer' => ['nullable', 'boolean'], 'accesses.*.stocktake' => ['nullable', 'boolean']]);
        $validUserIds = User::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
        foreach (($data['accesses'] ?? []) as $userId => $rights) {
            if (! in_array((string) $userId, $validUserIds, true)) continue;
            $values = collect(['view', 'issue', 'receive', 'transfer', 'stocktake'])->mapWithKeys(fn ($key) => ['can_'.$key => (bool) ($rights[$key] ?? false)])->all();
            if (array_filter($values)) $values['can_view'] = true;
            if (! array_filter($values)) { WarehouseSiteAccess::query()->where('site_id', $site->id)->where('user_id', $userId)->delete(); continue; }
            WarehouseSiteAccess::query()->updateOrCreate(['site_id' => $site->id, 'user_id' => $userId], ['company_id' => $companyId] + $values);
        }
        return back()->with('success', 'دسترسی انبار سایت «'.$site->name.'» ذخیره شد.');
    }
}
