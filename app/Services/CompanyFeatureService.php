<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;

/**
 * Central source of truth for tenant modules.
 *
 * A plan provides a sensible starting point. A company administrator can then
 * enable or disable individual add-ons without inventing a new plan for every
 * commercial agreement.
 */
final class CompanyFeatureService
{
    public const FEATURES = [
        'asset_core' => [
            'label' => 'هسته مدیریت اموال',
            'description' => 'ثبت، مشاهده، کدگذاری و تحویل اموال ثابت.',
        ],
        'organization' => [
            'label' => 'ساختار سازمانی',
            'description' => 'پرسنل، سایت‌ها، واحدها و موقعیت‌های مکانی.',
        ],
        'reports' => [
            'label' => 'گزارش‌های پایه',
            'description' => 'گزارش‌های اموال و خروجی‌های مدیریتی پایه.',
        ],
        'initial_setup' => [
            'label' => 'راه‌اندازی و ورود گروهی',
            'description' => 'ثبت موجودی اولیه و ورود اکسل دارایی‌های ثابت.',
        ],
        'asset_movements' => [
            'label' => 'گردش و جابه‌جایی اموال',
            'description' => 'درخواست انتقال، عودت و گردش کنترل‌شده اموال.',
        ],
        'warehouse' => [
            'label' => 'انبار سایت و درخواست کالا',
            'description' => 'موجودی اموال ثابت هر سایت، تحویل و دسترسی انبارداران.',
        ],
        'stocktake' => [
            'label' => 'انبارگردانی',
            'description' => 'انبارگردانی فیزیکی، شمارش و رسیدگی به مغایرت.',
        ],
        'barcode' => [
            'label' => 'اسکن بارکد و پلاک',
            'description' => 'جست‌وجوی سریع دارایی با دوربین یا بارکدخوان.',
        ],
        'repairs' => [
            'label' => 'تعمیر و نگهداری',
            'description' => 'ثبت، پیگیری و گزارش تعمیرات اموال.',
        ],
        'workflows' => [
            'label' => 'طراحی گردش‌کار',
            'description' => 'طراحی مرحله‌های تأیید و اجرای Workflow.',
        ],
        'charts' => [
            'label' => 'نمودارساز مدیریتی',
            'description' => 'ساخت و نگهداری نمودارهای اختصاصی مدیران.',
        ],
        'audit_history' => [
            'label' => 'سابقه ورود و فعالیت',
            'description' => 'مشاهده تاریخچه ورود و فعالیت‌های مهم کاربران.',
        ],
        'company_storage' => [
            'label' => 'فضای ذخیره‌سازی اختصاصی',
            'description' => 'پیکربندی فضای نگهداری فایل شرکت.',
        ],
        'custom_branding' => [
            'label' => 'هویت بصری اختصاصی',
            'description' => 'لوگو و رنگ‌بندی اختصاصی شرکت در سامانه.',
        ],
    ];

    private const PLAN_FEATURES = [
        'basic' => [
            'asset_core', 'organization', 'reports', 'initial_setup',
        ],
        'professional' => [
            'asset_core', 'organization', 'reports', 'initial_setup',
            'asset_movements', 'warehouse', 'stocktake', 'barcode',
            'repairs', 'audit_history',
        ],
        'enterprise' => [
            'asset_core', 'organization', 'reports', 'initial_setup',
            'asset_movements', 'warehouse', 'stocktake', 'barcode',
            'repairs', 'workflows', 'charts', 'audit_history',
            'company_storage', 'custom_branding',
        ],
    ];

    public function definitions(): array
    {
        return self::FEATURES;
    }

    public function planFeatures(string $plan): array
    {
        $plan = strtolower(trim($plan));

        if (in_array($plan, ['enterprise-demo', 'demo', 'internal'], true)) {
            $plan = 'enterprise';
        }

        return self::PLAN_FEATURES[$plan] ?? self::PLAN_FEATURES['basic'];
    }

    public function featureStates(Company $company): array
    {
        $defaults = array_fill_keys($this->planFeatures($company->plan), true);
        $overrides = is_array($company->feature_overrides) ? $company->feature_overrides : [];

        $states = [];
        foreach (array_keys(self::FEATURES) as $feature) {
            $states[$feature] = array_key_exists($feature, $overrides)
                ? filter_var($overrides[$feature], FILTER_VALIDATE_BOOLEAN)
                : (bool) ($defaults[$feature] ?? false);
        }

        // The product cannot meaningfully operate without these foundations.
        foreach (['asset_core', 'organization'] as $foundation) {
            $states[$foundation] = true;
        }

        return $states;
    }

    public function enabled(Company $company, string $feature): bool
    {
        return (bool) ($this->featureStates($company)[$feature] ?? false);
    }

    public function enabledForUser(?User $user, string $feature): bool
    {
        if ($user === null || $user->isSuperAdmin()) {
            return true;
        }

        return $user->company !== null && $this->enabled($user->company, $feature);
    }

    /** Maps a route to the commercial module that owns it. */
    public function featureForRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        return match (true) {
            str_starts_with($routeName, 'inventory-requests.'),
            str_starts_with($routeName, 'warehouse-'),
            $routeName === 'approvals.warehouse-allocations.store' => 'warehouse',
            str_starts_with($routeName, 'stocktakes.') => 'stocktake',
            str_starts_with($routeName, 'asset-scanner.') => 'barcode',
            str_starts_with($routeName, 'asset-repairs.'),
            $routeName === 'reports.repairs' => 'repairs',
            str_starts_with($routeName, 'asset-movement-requests.'),
            str_starts_with($routeName, 'asset-transactions.') => 'asset_movements',
            str_starts_with($routeName, 'workflows.'),
            str_starts_with($routeName, 'specialist-approvals.') => 'workflows',
            str_starts_with($routeName, 'workspace.charts') => 'charts',
            $routeName === 'workspace.history' => 'audit_history',
            str_starts_with($routeName, 'company-storage-profiles.') => 'company_storage',
            str_starts_with($routeName, 'bulk-import.'),
            str_starts_with($routeName, 'initial-setup.') => 'initial_setup',
            default => null,
        };
    }
}
