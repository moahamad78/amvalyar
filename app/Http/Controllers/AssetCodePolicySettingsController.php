<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\AssetCodePolicySetting;
use App\Models\AssetCodeSequence;
use App\Models\AssetType;
use App\Models\Company;
use App\Services\AssetCode\AssetCodePolicyEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetCodePolicySettingsController extends Controller
{
    public function index(
        Request $request,
        AssetCodePolicyEngine $engine
    ): View {

        $company =
            $this->resolveCompany(
                $request
            );


        $settings =
            AssetCodePolicySetting::query()
                ->where(
                    'company_id',
                    $company->id
                )
                ->first()
            ??
            AssetCodePolicySetting::defaultsFor(
                (int) $company->id
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


        $category =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'id'
                )
                ->first();


        $type =
            $category !== null

                ? AssetType::withoutGlobalScopes()
                    ->where(
                        'company_id',
                        $company->id
                    )
                    ->where(
                        'asset_category_id',
                        $category->id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->orderBy(
                        'sort_order'
                    )
                    ->orderBy(
                        'id'
                    )
                    ->first()

                : null;


        $preview =
            $engine
                ->buildPolicy(
                    categoryCode:
                        $category?->code,

                    typeCode:
                        $type?->code,

                    settings:
                        $settings,
                )
                ->format(
                    1
                );


        $sequences =
            AssetCodeSequence::query()
                ->where(
                    'company_id',
                    $company->id
                )
                ->orderBy(
                    'prefix'
                )
                ->get();


        return view(
            'asset_settings.code_policy.index',
            compact(
                'company',
                'companies',
                'settings',
                'preview',
                'sequences',
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
                'mode' => [
                    'required',
                    Rule::in([
                        AssetCodePolicySetting::MODE_SEMANTIC,
                        AssetCodePolicySetting::MODE_LEGACY,
                    ]),
                ],

                'fallback_prefix' => [
                    'required',
                    'string',
                    'max:50',
                    'regex:/^[A-Za-z0-9_-]+$/',
                ],

                'padding' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:12',
                ],

                'separator' => [
                    'required',
                    Rule::in([
                        '-',
                        '_',
                        '/',
                        '.',
                    ]),
                ],
            ]);


        $includeCategory =
            $request->boolean(
                'include_category'
            );


        $includeType =
            $request->boolean(
                'include_type'
            );


        if (
            $validated['mode']
            ===
            AssetCodePolicySetting::MODE_SEMANTIC
            &&
            !$includeCategory
            &&
            !$includeType
        ) {

            throw ValidationException::withMessages([
                'include_category' =>
                    'در حالت معنایی حداقل دسته دارایی یا نوع دارایی باید در کد استفاده شود.',
            ]);
        }


        AssetCodePolicySetting::query()
            ->updateOrCreate(
                [
                    'company_id' =>
                        $company->id,
                ],
                [
                    'mode' =>
                        $validated['mode'],

                    'fallback_prefix' =>
                        strtoupper(
                            $validated[
                                'fallback_prefix'
                            ]
                        ),

                    'padding' =>
                        (int) $validated[
                            'padding'
                        ],

                    'separator' =>
                        $validated[
                            'separator'
                        ],

                    'include_category' =>
                        $includeCategory,

                    'include_type' =>
                        $includeType,
                ]
            );


        return redirect()
            ->route(
                'asset-settings.code-policy.index',
                $request->user()
                    ->isSuperAdmin()
                        ? [
                            'company_id' =>
                                $company->id,
                        ]
                        : []
            )
            ->with(
                'success',
                'سیاست کدگذاری اموال با موفقیت ذخیره شد.'
            );
    }


    private function resolveCompany(
        Request $request
    ): Company {

        $user =
            $request->user();


        if ($user->isSuperAdmin()) {

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


        return Company::query()
            ->findOrFail(
                (int) $user->company_id
            );
    }
}