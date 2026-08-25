<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCodeFormulaSetting;
use App\Models\AssetCodePolicySetting;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Site;
use App\Services\AssetCode\AssetCodeFormulaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AssetCodeSettingsController extends Controller
{
    public function index(
        Request $request,
        AssetCodeFormulaService $formulaService
    ): View {
        $company = $this->resolveCompany($request);

        $companies = $request->user()->isSuperAdmin()
            ? Company::query()->orderBy('name')->get()
            : collect();

        $formula = $formulaService->settingsFor((int) $company->id);

        $siteCount = Site::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->count();

        $categoryMappingCount = AssetCategoryCodingMapping::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('coding_code')
            ->where('coding_code', '!=', '')
            ->count();

        $typeCodingCount = AssetType::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('coding_code')
            ->where('coding_code', '!=', '')
            ->count();

        $legacyPolicy = AssetCodePolicySetting::query()
            ->where('company_id', $company->id)
            ->first();

        $persistedFormula = AssetCodeFormulaSetting::query()
            ->where('company_id', $company->id)
            ->exists();

        $site = Site::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        $mapping = AssetCategoryCodingMapping::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('coding_code')
            ->where('coding_code', '!=', '')
            ->orderBy('id')
            ->first();

        $type = $mapping !== null
            ? AssetType::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('asset_category_id', $mapping->asset_category_id)
                ->where('is_active', true)
                ->whereNotNull('coding_code')
                ->where('coding_code', '!=', '')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first()
            : null;

        $preview = ($site !== null && $mapping !== null && $type !== null)
            ? $formulaService->preview(
                $formula,
                (string) $site->code,
                (string) $mapping->coding_code,
                (string) $type->coding_code,
                1
            )
            : $formulaService->preview($formula);

        $masterDataReady =
            $siteCount > 0
            && $categoryMappingCount > 0
            && $typeCodingCount > 0;

        return view('asset_settings.code.index', compact(
            'company',
            'companies',
            'formula',
            'persistedFormula',
            'legacyPolicy',
            'siteCount',
            'categoryMappingCount',
            'typeCodingCount',
            'masterDataReady',
            'preview'
        ));
    }

    private function resolveCompany(Request $request): Company
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            return Company::query()->findOrFail((int) $user->company_id);
        }

        $companyId = (int) (
            $request->query('company_id')
            ?? $request->input('company_id')
            ?? $user->company_id
            ?? 0
        );

        if ($companyId <= 0) {
            $companyId = (int) Company::query()
                ->orderBy('id')
                ->value('id');
        }

        return Company::query()->findOrFail($companyId);
    }
}
