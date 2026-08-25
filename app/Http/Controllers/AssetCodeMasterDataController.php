<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetCodeMasterDataController extends Controller
{
    public function index(
        Request $request
    ): View {

        $company =
            $this->resolveCompany(
                $request
            );


        $companies =
            $request->user()
                ->isSuperAdmin()

                ? Company::query()
                    ->orderBy(
                        'name'
                    )
                    ->get()

                : collect();


        $sites =
            Site::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'name'
                )
                ->get();


        $categories =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'name'
                )
                ->get();


        $categoryMappings =
            AssetCategoryCodingMapping::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->get()
                ->keyBy(
                    'asset_category_id'
                );


        $types =
            AssetType::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->with(
                    'category'
                )
                ->orderBy(
                    'asset_category_id'
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'name'
                )
                ->get();


        $exampleSite =
            $sites
                ->first(
                    fn (Site $site): bool =>
                        trim(
                            (string) (
                                $site->code
                                ?? ''
                            )
                        )
                        !==
                        ''
                );


        $exampleCategory =
            $categories
                ->first(
                    function (
                        AssetCategory $category
                    ) use (
                        $categoryMappings
                    ): bool {

                        $mapping =
                            $categoryMappings
                                ->get(
                                    $category->id
                                );

                        return
                            $mapping !== null
                            &&
                            trim(
                                (string) $mapping->coding_code
                            )
                            !==
                            '';
                    }
                );


        $exampleMapping =
            $exampleCategory !== null
                ? $categoryMappings
                    ->get(
                        $exampleCategory->id
                    )
                : null;


        $exampleType =
            $types
                ->first(
                    function (
                        AssetType $type
                    ) use (
                        $exampleCategory
                    ): bool {

                        if (
                            $exampleCategory !== null
                            &&
                            (int) $type->asset_category_id
                            !==
                            (int) $exampleCategory->id
                        ) {
                            return false;
                        }

                        return
                            trim(
                                (string) (
                                    $type->coding_code
                                    ?? ''
                                )
                            )
                            !==
                            '';
                    }
                );


        $preview =
            (
                $exampleSite !== null
                &&
                $exampleMapping !== null
                &&
                $exampleType !== null
            )
                ? implode(
                    '-',
                    [
                        $exampleSite->code,
                        $exampleMapping->coding_code,
                        $exampleType->coding_code,
                        '0001',
                    ]
                )
                : null;


        return view(
            'asset_settings.code_master_data.index',
            compact(
                'company',
                'companies',
                'sites',
                'categories',
                'categoryMappings',
                'types',
                'preview',
            )
        );
    }


    public function update(
        Request $request
    ): RedirectResponse {

        $company =
            $this->resolveCompany(
                $request
            );


        $validated =
            $request->validate([
                'category_codes' => [
                    'nullable',
                    'array',
                ],

                'category_codes.*' => [
                    'nullable',
                    'string',
                    'max:30',
                    'regex:/^[0-9]+$/',
                ],

                'type_codes' => [
                    'nullable',
                    'array',
                ],

                'type_codes.*' => [
                    'nullable',
                    'string',
                    'max:30',
                    'regex:/^[0-9]+$/',
                ],
            ]);


        $categoryCodes =
            $validated[
                'category_codes'
            ]
            ??
            [];


        $typeCodes =
            $validated[
                'type_codes'
            ]
            ??
            [];


        DB::transaction(
            function () use (
                $company,
                $categoryCodes,
                $typeCodes
            ): void {

                $this->syncCategoryCodes(
                    company:
                        $company,

                    categoryCodes:
                        $categoryCodes
                );


                $this->syncTypeCodes(
                    company:
                        $company,

                    typeCodes:
                        $typeCodes
                );
            },
            3
        );


        return redirect()
            ->route(
                'asset-settings.code-master-data.index',
                request()
                    ->user()
                    ->isSuperAdmin()

                    ? [
                        'company_id' =>
                            $company->id,
                    ]

                    : []
            )
            ->with(
                'success',
                'کدهای مبنای اموال با موفقیت ذخیره شدند.'
            );
    }


    private function syncCategoryCodes(
        Company $company,
        array $categoryCodes
    ): void {

        $validCategoryIds =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->pluck(
                    'id'
                )
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->all();


        $usedCodes = [];


        foreach (
            $categoryCodes
            as
            $categoryId =>
            $rawCode
        ) {

            $categoryId =
                (int) $categoryId;


            if (
                !in_array(
                    $categoryId,
                    $validCategoryIds,
                    true
                )
            ) {

                throw ValidationException::withMessages([
                    'category_codes' =>
                        'یکی از دسته‌بندی‌های ارسال‌شده معتبر نیست.',
                ]);
            }


            $code =
                trim(
                    (string) (
                        $rawCode
                        ?? ''
                    )
                );


            if ($code === '') {

                AssetCategoryCodingMapping::withoutGlobalScopes()
                    ->where(
                        'company_id',
                        $company->id
                    )
                    ->where(
                        'asset_category_id',
                        $categoryId
                    )
                    ->delete();

                continue;
            }


            if (
                isset(
                    $usedCodes[
                        $code
                    ]
                )
            ) {

                throw ValidationException::withMessages([
                    'category_codes' =>
                        'کد ماهیت اصلی «'
                        .
                        $code
                        .
                        '» بیش از یک‌بار استفاده شده است.',
                ]);
            }


            $usedCodes[
                $code
            ] =
                true;


            AssetCategoryCodingMapping::withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'company_id' =>
                            $company->id,

                        'asset_category_id' =>
                            $categoryId,
                    ],
                    [
                        'coding_code' =>
                            $code,

                        'is_active' =>
                            true,
                    ]
                );
        }
    }


    private function syncTypeCodes(
        Company $company,
        array $typeCodes
    ): void {

        $types =
            AssetType::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $company->id
                )
                ->get()
                ->keyBy(
                    'id'
                );


        $usedByCategory = [];


        foreach (
            $typeCodes
            as
            $typeId =>
            $rawCode
        ) {

            $typeId =
                (int) $typeId;


            /** @var AssetType|null $type */
            $type =
                $types->get(
                    $typeId
                );


            if ($type === null) {

                throw ValidationException::withMessages([
                    'type_codes' =>
                        'یکی از انواع دارایی ارسال‌شده متعلق به این شرکت نیست.',
                ]);
            }


            $code =
                trim(
                    (string) (
                        $rawCode
                        ?? ''
                    )
                );


            if ($code === '') {

                $type->coding_code =
                    null;

                $type->save();

                continue;
            }


            $categoryId =
                (int) $type->asset_category_id;


            if (
                isset(
                    $usedByCategory[
                        $categoryId
                    ][
                        $code
                    ]
                )
            ) {

                throw ValidationException::withMessages([
                    'type_codes' =>
                        'کد ماهیت فرعی «'
                        .
                        $code
                        .
                        '» در یک دسته‌بندی بیش از یک‌بار استفاده شده است.',
                ]);
            }


            $usedByCategory[
                $categoryId
            ][
                $code
            ] =
                true;


            $type->coding_code =
                $code;

            $type->save();
        }
    }


    private function resolveCompany(
        Request $request
    ): Company {

        $user =
            $request->user();


        if (
            !$user->isSuperAdmin()
        ) {

            return Company::query()
                ->findOrFail(
                    (int) $user->company_id
                );
        }


        $companyId =
            (int) (
                $request->input(
                    'company_id'
                )
                ??
                $request->query(
                    'company_id'
                )
                ??
                $user->company_id
                ??
                0
            );


        if ($companyId <= 0) {

            $companyId =
                (int) Company::query()
                    ->orderBy(
                        'id'
                    )
                    ->value(
                        'id'
                    );
        }


        return Company::query()
            ->findOrFail(
                $companyId
            );
    }
}