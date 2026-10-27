<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use App\Models\WarehouseSiteAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class WarehouseSiteAccessService
{
    /** @var array<string,string> */
    private const COLUMN = [
        'view' => 'can_view', 'issue' => 'can_issue', 'receive' => 'can_receive',
        'transfer' => 'can_transfer', 'stocktake' => 'can_stocktake',
    ];

    public function isSiteScoped(User $user): bool
    {
        return ! $user->isSuperAdmin() && in_array($user->role?->name, ['warehouse_manager', 'warehouse_supervisor'], true);
    }

    public function isCompanyWide(User $user): bool
    {
        return $user->isSuperAdmin() || in_array($user->role?->name, [
            'admin', 'company_admin', 'asset_manager', 'chief_asset_keeper',
        ], true);
    }

    public function siteIds(User $user, string $ability = 'view'): Collection
    {
        if (! isset(self::COLUMN[$ability])) {
            throw new \InvalidArgumentException('Unknown warehouse ability.');
        }
        if ($this->isCompanyWide($user)) {
            return Site::withoutGlobalScopes()->where('company_id', $user->company_id)->where('is_active', true)->pluck('id');
        }
        if (! $this->isSiteScoped($user)) {
            return collect();
        }
        return WarehouseSiteAccess::query()->where('company_id', $user->company_id)
            ->where('user_id', $user->id)->where(self::COLUMN[$ability], true)->pluck('site_id');
    }

    public function can(User $user, int|Site|null $site, string $ability): bool
    {
        $siteId = $site instanceof Site ? $site->id : $site;
        if ($siteId === null || ! isset(self::COLUMN[$ability])) return false;
        if ($this->isCompanyWide($user)) return true;
        if (! $this->isSiteScoped($user)) return false;
        return WarehouseSiteAccess::query()->where('company_id', $user->company_id)->where('user_id', $user->id)
            ->where('site_id', $siteId)->where(self::COLUMN[$ability], true)->exists();
    }

    public function assertCan(User $user, int|Site|null $site, string $ability): void
    {
        if (! $this->can($user, $site, $ability)) {
            throw ValidationException::withMessages(['warehouse_site' => 'برای انبار سایت انتخاب‌شده دسترسی «'.$this->label($ability).'» ندارید.']);
        }
    }

    private function label(string $ability): string
    {
        return ['view' => 'مشاهده', 'issue' => 'تحویل', 'receive' => 'دریافت', 'transfer' => 'انتقال', 'stocktake' => 'انبارگردانی'][$ability] ?? $ability;
    }
}
