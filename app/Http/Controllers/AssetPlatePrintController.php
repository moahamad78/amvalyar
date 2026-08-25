<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetPlateTemplate;
use App\Models\Company;
use App\Services\AssetPlatePrintRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class AssetPlatePrintController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->resolveCompany($request);

        $templates = AssetPlateTemplate::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $search = trim((string) $request->query('q', ''));

        $assets = Asset::query()
            ->where('company_id', $company->id)
            ->whereNotNull('asset_code')
            ->where('asset_code', '<>', '')
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($inner) use ($search): void {
                            $inner
                                ->where('asset_code', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%")
                                ->orWhere('serial_number', 'like', "%{$search}%");
                        }
                    );
                }
            )
            ->orderBy('asset_code')
            ->paginate(30)
            ->withQueryString();

        $companies = $request->user()->isSuperAdmin()
            ? Company::query()->orderBy('name')->get()
            : collect();

        return view(
            'asset_plates.index',
            compact(
                'company',
                'companies',
                'templates',
                'assets',
                'search'
            )
        );
    }

    public function preview(
        Request $request,
        AssetPlatePrintRenderer $renderer
    ): View {
        $company = $this->resolveCompany($request);

        $validated = $request->validate([
            'template_id' => ['required', 'integer'],
            'asset_ids' => ['required', 'array', 'min:1', 'max:100'],
            'asset_ids.*' => ['required', 'integer'],
            'mode' => ['nullable', 'in:label,sheet'],
        ]);

        $template = AssetPlateTemplate::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->findOrFail((int) $validated['template_id']);

        $ids = collect($validated['asset_ids'])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $assets = Asset::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $ids->all())
            ->whereNotNull('asset_code')
            ->with(['codingSite', 'currentSite', 'currentLocation'])
            ->orderBy('asset_code')
            ->get();

        if ($assets->count() !== $ids->count()) {
            abort(404);
        }

        $plates = $this->renderPlates($assets, $template, $renderer);
        $mode = (string) ($validated['mode'] ?? 'label');

        return view(
            'asset_plates.print',
            compact('company', 'template', 'plates', 'mode')
        );
    }

    public function single(
        Request $request,
        Asset $asset,
        AssetPlatePrintRenderer $renderer
    ): View {
        $company = $this->resolveCompany($request);

        if ((int) $asset->company_id !== (int) $company->id) {
            abort(404);
        }

        if (trim((string) $asset->asset_code) === '') {
            abort(422, 'ابتدا کد دائمی اموال را صادر کنید.');
        }

        $templateId = (int) $request->query('template_id', 0);

        $templateQuery = AssetPlateTemplate::query()
            ->where('company_id', $company->id)
            ->where('is_active', true);

        $template = $templateId > 0
            ? $templateQuery->findOrFail($templateId)
            : $templateQuery
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->firstOrFail();

        $asset->load(['codingSite', 'currentSite', 'currentLocation']);

        $plates = collect([
            $renderer->render($asset, $template),
        ]);

        $mode = 'label';

        return view(
            'asset_plates.print',
            compact('company', 'template', 'plates', 'mode')
        );
    }

    private function renderPlates(
        Collection $assets,
        AssetPlateTemplate $template,
        AssetPlatePrintRenderer $renderer
    ): Collection {
        return $assets->map(
            static fn (Asset $asset): array => $renderer->render($asset, $template)
        );
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
            $companyId = (int) Company::query()
                ->orderBy('id')
                ->value('id');
        }

        return Company::query()->findOrFail($companyId);
    }
}