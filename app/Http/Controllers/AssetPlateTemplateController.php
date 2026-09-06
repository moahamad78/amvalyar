<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssetPlateTemplate;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetPlateTemplateController extends Controller
{
    private const ALLOWED_FIELDS = [
        'company_name',
        'asset_code',
        'title',
        'brand',
        'model',
        'serial_number',
        'coding_site',
        'current_site',
        'current_location',
    ];

    private const ALLOWED_TYPES = [
        'field',
        'text',
        'qr',
        'barcode',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($company === null) {
            return $this->redirectToCompanyCreation();
        }

        $companies = $request->user()->isSuperAdmin()
            ? Company::query()->orderBy('name')->get()
            : collect();

        $templates = AssetPlateTemplate::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view(
            'asset_settings.plate_templates.index',
            compact(
                'company',
                'companies',
                'templates'
            )
        );
    }

    public function create(Request $request): View|RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($company === null) {
            return $this->redirectToCompanyCreation();
        }

        $template = new AssetPlateTemplate([
            'company_id' => $company->id,
            'name' => 'پلاک استاندارد',
            'width_mm' => 50,
            'height_mm' => 30,
            'orientation' => 'landscape',
            'elements' => AssetPlateTemplate::defaultElements(),
            'is_default' => false,
            'is_active' => true,
        ]);

        return view(
            'asset_settings.plate_templates.editor',
            compact(
                'company',
                'template'
            )
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $data = $this->validatedTemplate($request);

        $template = DB::transaction(
            function () use ($company, $data): AssetPlateTemplate {
                if ($data['is_default']) {
                    AssetPlateTemplate::query()
                        ->where('company_id', $company->id)
                        ->update(['is_default' => false]);
                }

                return AssetPlateTemplate::query()->create([
                    'company_id' => $company->id,
                    ...$data,
                ]);
            },
            3
        );

        return redirect()
            ->route(
                'asset-settings.plate-templates.edit',
                $template
            )
            ->with('success', 'قالب پلاک با موفقیت ساخته شد.');
    }

    public function edit(
        Request $request,
        AssetPlateTemplate $plateTemplate
    ): View {
        $company = $this->resolveCompany($request);

        $this->ensureVisible(
            $company,
            $plateTemplate
        );

        $template = $plateTemplate;

        return view(
            'asset_settings.plate_templates.editor',
            compact(
                'company',
                'template'
            )
        );
    }

    public function update(
        Request $request,
        AssetPlateTemplate $plateTemplate
    ): RedirectResponse {
        $company = $this->resolveCompany($request);

        $this->ensureVisible(
            $company,
            $plateTemplate
        );

        $data = $this->validatedTemplate($request);

        DB::transaction(
            function () use ($company, $plateTemplate, $data): void {
                if ($data['is_default']) {
                    AssetPlateTemplate::query()
                        ->where('company_id', $company->id)
                        ->whereKeyNot($plateTemplate->id)
                        ->update(['is_default' => false]);
                }

                $plateTemplate->update($data);
            },
            3
        );

        return back()
            ->with('success', 'طراحی پلاک ذخیره شد.');
    }

    public function destroy(
        Request $request,
        AssetPlateTemplate $plateTemplate
    ): RedirectResponse {
        $company = $this->resolveCompany($request);

        $this->ensureVisible(
            $company,
            $plateTemplate
        );

        $wasDefault = $plateTemplate->is_default;
        $plateTemplate->delete();

        if ($wasDefault) {
            $replacement = AssetPlateTemplate::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($replacement !== null) {
                $replacement->is_default = true;
                $replacement->save();
            }
        }

        return redirect()
            ->route('asset-settings.plate-templates.index')
            ->with('success', 'قالب پلاک حذف شد.');
    }

    private function validatedTemplate(Request $request): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'width_mm' => [
                'required',
                'numeric',
                'min:10',
                'max:500',
            ],
            'height_mm' => [
                'required',
                'numeric',
                'min:10',
                'max:500',
            ],
            'orientation' => [
                'required',
                'in:landscape,portrait',
            ],
            'elements_json' => [
                'required',
                'string',
                'json',
            ],
        ]);

        $elements = json_decode(
            $validated['elements_json'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! is_array($elements) || count($elements) > 50) {
            throw ValidationException::withMessages([
                'elements_json' => 'ساختار اجزای پلاک معتبر نیست.',
            ]);
        }

        $clean = [];

        foreach ($elements as $index => $element) {
            if (! is_array($element)) {
                throw ValidationException::withMessages([
                    'elements_json' => 'یکی از اجزای پلاک معتبر نیست.',
                ]);
            }

            $type = (string) ($element['type'] ?? '');
            $field = (string) ($element['field'] ?? '');

            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                throw ValidationException::withMessages([
                    'elements_json' => 'نوع یکی از اجزای پلاک پشتیبانی نمی‌شود.',
                ]);
            }

            if (
                $type !== 'text'
                && ! in_array(
                    $field,
                    self::ALLOWED_FIELDS,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'elements_json' => 'فیلد یکی از اجزای پلاک معتبر نیست.',
                ]);
            }

            $x = $this->boundedNumber(
                $element['x'] ?? 0,
                0,
                500
            );

            $y = $this->boundedNumber(
                $element['y'] ?? 0,
                0,
                500
            );

            $w = $this->boundedNumber(
                $element['w'] ?? 10,
                2,
                500
            );

            $h = $this->boundedNumber(
                $element['h'] ?? 5,
                2,
                500
            );

            $fontSize = (int) ($element['font_size'] ?? 9);
            $fontSize = max(5, min(72, $fontSize));

            $align = (string) ($element['align'] ?? 'center');

            if (
                ! in_array(
                    $align,
                    [
                        'right',
                        'center',
                        'left',
                    ],
                    true
                )
            ) {
                $align = 'center';
            }

            $clean[] = [
                'id' => (string) (
                    $element['id']
                    ?? ('element_'.$index)
                ),
                'type' => $type,
                'field' => $field,
                'label' => mb_substr(
                    (string) (
                        $element['label']
                        ?? ''
                    ),
                    0,
                    120
                ),
                'text' => mb_substr(
                    (string) (
                        $element['text']
                        ?? ''
                    ),
                    0,
                    250
                ),
                'x' => $x,
                'y' => $y,
                'w' => $w,
                'h' => $h,
                'font_size' => $fontSize,
                'align' => $align,
                'bold' => (bool) (
                    $element['bold']
                    ?? false
                ),
            ];
        }

        return [
            'name' => trim($validated['name']),
            'width_mm' => (float) $validated['width_mm'],
            'height_mm' => (float) $validated['height_mm'],
            'orientation' => $validated['orientation'],
            'elements' => $clean,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function boundedNumber(
        mixed $value,
        float $min,
        float $max
    ): float {
        $number = (float) $value;

        return max(
            $min,
            min(
                $max,
                $number
            )
        );
    }

    private function ensureVisible(
        Company $company,
        AssetPlateTemplate $template
    ): void {
        if (
            (int) $template->company_id
            !==
            (int) $company->id
        ) {
            abort(404);
        }
    }

    private function resolveCompany(Request $request): ?Company
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return Company::query()
                ->findOrFail(
                    (int) $user->company_id
                );
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

        return $companyId > 0
            ? Company::query()->findOrFail($companyId)
            : null;
    }

    private function redirectToCompanyCreation(): RedirectResponse
    {
        return redirect()
            ->route('companies.create')
            ->withErrors([
                'company' => 'برای طراحی قالب پلاک، ابتدا یک شرکت ایجاد کنید.',
            ]);
    }
}
