<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssetAttributeDefinitionRequest;
use App\Models\AssetAttributeDefinition;
use App\Models\AssetAttributeOption;
use App\Models\AssetType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetAttributeController extends Controller
{
    public function index(
        Request $request,
        int $assetType
    ): View {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        $attributes =
            AssetAttributeDefinition::query()
                ->where(
                    'asset_type_id',
                    $type->id
                )
                ->where(
                    'company_id',
                    $type->company_id
                )
                ->with([
                    'options',
                ])
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'id'
                )
                ->get();


        return view(
            'asset_settings.attributes.index',
            compact(
                'type',
                'attributes'
            )
        );
    }


    public function create(
        Request $request,
        int $assetType
    ): View {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        return view(
            'asset_settings.attributes.create',
            compact(
                'type'
            )
        );
    }


    public function store(
        AssetAttributeDefinitionRequest $request,
        int $assetType
    ): RedirectResponse {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        $data =
            $request->validated();


        $this->ensureUniqueCode(
            type:
                $type,

            code:
                $data['code'],

            ignoreId:
                null
        );


        $this->ensureSelectOptions(
            $data
        );


        return DB::transaction(
            function () use (
                $request,
                $type,
                $data
            ): RedirectResponse {

                $definition =
                    AssetAttributeDefinition::query()
                        ->create([

                            'company_id' =>
                                $type->company_id,

                            'asset_type_id' =>
                                $type->id,

                            'name' =>
                                $data['name'],

                            'code' =>
                                strtoupper(
                                    $data['code']
                                ),

                            'data_type' =>
                                $data['data_type'],

                            'required_stage' =>
                                $data['required_stage'],

                            'unit' =>
                                $data['unit']
                                ?? null,

                            'placeholder' =>
                                $data['placeholder']
                                ?? null,

                            'help_text' =>
                                $data['help_text']
                                ?? null,

                            'sort_order' =>
                                (int) (
                                    $data['sort_order']
                                    ?? 10
                                ),

                            'is_active' =>
                                $request->boolean(
                                    'is_active'
                                ),
                        ]);


                $this->syncOptions(
                    $definition,
                    $data['options_text']
                    ?? null
                );


                return redirect()
                    ->route(
                        'asset-settings.attributes.index',
                        $type->id
                    )
                    ->with(
                        'success',
                        'ویژگی شناسنامه با موفقیت ایجاد شد.'
                    );
            }
        );
    }


    public function edit(
        Request $request,
        int $assetType,
        int $attribute
    ): View {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        $definition =
            $this->resolveAttribute(
                $type,
                $attribute
            );


        $definition->load(
            'options'
        );


        $optionsText =
            $definition->options
                ->filter(
                    fn ($option) =>
                        $option->is_active
                )
                ->map(
                    fn ($option) =>
                        $option->label
                        . '|'
                        . $option->value
                )
                ->implode(
                    PHP_EOL
                );


        return view(
            'asset_settings.attributes.edit',
            compact(
                'type',
                'definition',
                'optionsText'
            )
        );
    }


    public function update(
        AssetAttributeDefinitionRequest $request,
        int $assetType,
        int $attribute
    ): RedirectResponse {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        $definition =
            $this->resolveAttribute(
                $type,
                $attribute
            );


        $data =
            $request->validated();


        $this->ensureUniqueCode(
            type:
                $type,

            code:
                $data['code'],

            ignoreId:
                $definition->id
        );


        $this->ensureSelectOptions(
            $data
        );


        return DB::transaction(
            function () use (
                $request,
                $type,
                $definition,
                $data
            ): RedirectResponse {

                $definition->update([

                    'name' =>
                        $data['name'],

                    'code' =>
                        strtoupper(
                            $data['code']
                        ),

                    'data_type' =>
                        $data['data_type'],

                    'required_stage' =>
                        $data['required_stage'],

                    'unit' =>
                        $data['unit']
                        ?? null,

                    'placeholder' =>
                        $data['placeholder']
                        ?? null,

                    'help_text' =>
                        $data['help_text']
                        ?? null,

                    'sort_order' =>
                        (int) (
                            $data['sort_order']
                            ?? 10
                        ),

                    'is_active' =>
                        $request->boolean(
                            'is_active'
                        ),
                ]);


                $this->syncOptions(
                    $definition,
                    $data['options_text']
                    ?? null
                );


                return redirect()
                    ->route(
                        'asset-settings.attributes.index',
                        $type->id
                    )
                    ->with(
                        'success',
                        'ویژگی شناسنامه با موفقیت ویرایش شد.'
                    );
            }
        );
    }


    public function destroy(
        Request $request,
        int $assetType,
        int $attribute
    ): RedirectResponse {

        $type =
            $this->resolveType(
                $request,
                $assetType
            );


        $definition =
            $this->resolveAttribute(
                $type,
                $attribute
            );


        /*
        |--------------------------------------------------------------------------
        | Safe Delete
        |--------------------------------------------------------------------------
        |
        | اگر برای این ویژگی قبلاً مقداری ثبت شده باشد،
        | Definition را حذف نمی‌کنیم و فقط غیرفعال می‌کنیم.
        |
        */

        if (
            $definition->values()
                ->exists()
        ) {

            $definition->is_active =
                false;

            $definition->save();


            return back()->with(
                'success',
                'این ویژگی دارای سابقه بود و به جای حذف، غیرفعال شد.'
            );
        }


        $definition->delete();


        return back()->with(
            'success',
            'ویژگی با موفقیت حذف شد.'
        );
    }


    private function resolveType(
        Request $request,
        int $assetType
    ): AssetType {

        $type =
            AssetType::query()
                ->findOrFail(
                    $assetType
                );


        if (
            !$request->user()
                ->isSuperAdmin()
            &&
            (int) $type->company_id
            !==
            (int) $request->user()
                ->company_id
        ) {

            abort(404);
        }


        return $type;
    }


    private function resolveAttribute(
        AssetType $type,
        int $attribute
    ): AssetAttributeDefinition {

        return AssetAttributeDefinition::query()
            ->whereKey(
                $attribute
            )
            ->where(
                'asset_type_id',
                $type->id
            )
            ->where(
                'company_id',
                $type->company_id
            )
            ->firstOrFail();
    }


    private function ensureUniqueCode(
        AssetType $type,
        string $code,
        ?int $ignoreId
    ): void {

        $normalized =
            strtoupper(
                trim(
                    $code
                )
            );


        $exists =
            AssetAttributeDefinition::query()
                ->where(
                    'company_id',
                    $type->company_id
                )
                ->where(
                    'asset_type_id',
                    $type->id
                )
                ->where(
                    'code',
                    $normalized
                )
                ->when(
                    $ignoreId !== null,
                    fn ($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $ignoreId
                        )
                )
                ->exists();


        if ($exists) {

            throw ValidationException::withMessages([
                'code' =>
                    'این کد ویژگی قبلاً برای این نوع دارایی استفاده شده است.',
            ]);
        }
    }


    private function ensureSelectOptions(
        array $data
    ): void {

        if (
            ($data['data_type'] ?? null)
            !==
            'select'
        ) {

            return;
        }


        if (
            trim(
                (string) (
                    $data['options_text']
                    ?? ''
                )
            )
            ===
            ''
        ) {

            throw ValidationException::withMessages([
                'options_text' =>
                    'برای فیلد انتخابی حداقل یک گزینه تعریف کنید.',
            ]);
        }
    }


    private function syncOptions(
        AssetAttributeDefinition $definition,
        ?string $text
    ): void {

        if (
            $definition->data_type
            !==
            'select'
        ) {

            $definition->options()
                ->update([
                    'is_active' =>
                        false,
                ]);

            return;
        }


        $lines =
            preg_split(
                '/\r\n|\r|\n/',
                (string) $text
            );


        $wantedValues =
            [];


        $sortOrder =
            10;


        foreach ($lines as $line) {

            $line =
                trim(
                    $line
                );


            if ($line === '') {
                continue;
            }


            $parts =
                explode(
                    '|',
                    $line,
                    2
                );


            $label =
                trim(
                    $parts[0]
                );


            $value =
                trim(
                    $parts[1]
                    ?? $parts[0]
                );


            if (
                $label === ''
                ||
                $value === ''
            ) {
                continue;
            }


            $wantedValues[] =
                $value;


            AssetAttributeOption::query()
                ->updateOrCreate(
                    [
                        'asset_attribute_definition_id' =>
                            $definition->id,

                        'value' =>
                            $value,
                    ],
                    [
                        'label' =>
                            $label,

                        'is_active' =>
                            true,

                        'sort_order' =>
                            $sortOrder,
                    ]
                );


            $sortOrder +=
                10;
        }


        $definition->options()
            ->when(
                $wantedValues !== [],
                fn ($query) =>
                    $query->whereNotIn(
                        'value',
                        $wantedValues
                    )
            )
            ->when(
                $wantedValues === [],
                fn ($query) =>
                    $query
            )
            ->update([
                'is_active' =>
                    false,
            ]);
    }
}