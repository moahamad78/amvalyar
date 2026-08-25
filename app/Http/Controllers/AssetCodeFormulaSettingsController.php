<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCodeFormulaSetting;
use App\Models\AssetCodeSequence;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Site;
use App\Services\AssetCode\AssetCodeFormulaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetCodeFormulaSettingsController extends Controller
{
    public function index(
        Request $request,
        AssetCodeFormulaService $formulaService
    ): View {
        $company = $this->resolveCompany($request);
        $settings = $formulaService->settingsFor((int) $company->id);

        $companies = $request->user()->isSuperAdmin()
            ? Company::query()->orderBy('name')->get()
            : collect();

        $site = Site::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('code')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        $mapping = AssetCategoryCodingMapping::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $type = $mapping !== null
            ? AssetType::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('asset_category_id', $mapping->asset_category_id)
                ->where('is_active', true)
                ->whereNotNull('coding_code')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first()
            : null;

        $preview = ($site !== null && $mapping !== null && $type !== null)
            ? $formulaService->preview(
                $settings,
                (string) $site->code,
                (string) $mapping->coding_code,
                (string) $type->coding_code,
                1
            )
            : $formulaService->preview($settings);

        $sequences = AssetCodeSequence::query()
            ->where('company_id', $company->id)
            ->orderBy('prefix')
            ->get();

        return view('asset_settings.code_formula.index', compact(
            'company',
            'companies',
            'settings',
            'preview',
            'sequences'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        $validated = $request->validate([
            'segment_order' => ['required', 'array', 'size:4'],
            'segment_order.*' => [
                'required',
                Rule::in(AssetCodeFormulaSetting::allowedSegments()),
            ],
            'separator' => [
                'nullable',
                Rule::in(['-', '_', '.', '/', '']),
            ],
            'site_length' => ['required', 'integer', 'min:1', 'max:30'],
            'category_length' => ['required', 'integer', 'min:1', 'max:30'],
            'type_length' => ['required', 'integer', 'min:1', 'max:30'],
            'serial_length' => ['required', 'integer', 'min:1', 'max:12'],
            'sequence_scope' => [
                'required',
                Rule::in(AssetCodeFormulaSetting::allowedScopes()),
            ],
        ]);

        $order = array_values($validated['segment_order']);

        if (count(array_unique($order)) !== 4) {
            throw ValidationException::withMessages([
                'segment_order' => 'هر بخش فرمول باید دقیقاً یک‌بار استفاده شود.',
            ]);
        }

        AssetCodeFormulaSetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'segment_order' => $order,
                'separator' => (string) ($validated['separator'] ?? ''),
                'site_length' => (int) $validated['site_length'],
                'category_length' => (int) $validated['category_length'],
                'type_length' => (int) $validated['type_length'],
                'serial_length' => (int) $validated['serial_length'],
                'sequence_scope' => $validated['sequence_scope'],
                'enforce_segment_lengths' => $request->boolean('enforce_segment_lengths'),
            ]
        );

        return redirect()
            ->route(
                'asset-settings.code-formula.index',
                $request->user()->isSuperAdmin()
                    ? ['company_id' => $company->id]
                    : []
            )
            ->with('success', 'فرمول کدگذاری اموال با موفقیت ذخیره شد.');
    }

    private function resolveCompany(Request $request): Company
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            return Company::query()->findOrFail((int) $user->company_id);
        }

        $companyId = (int) (
            $request->input('company_id')
            ?? $request->query('company_id')
            ?? $user->company_id
            ?? 0
        );

        if ($companyId <= 0) {
            $companyId = (int) Company::query()->orderBy('id')->value('id');
        }

        return Company::query()->findOrFail($companyId);
    }
}
