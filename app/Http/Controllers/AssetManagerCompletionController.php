<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Services\AssetCode\AssetCodeIssuanceService;
use App\Models\Site;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\User;
use App\Services\AssetAttributeValueService;
use App\Services\AssetCompletenessService;
use App\Services\AssetPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AssetManagerCompletionController extends Controller
{
    public function edit(
        Request $request,
        Asset $asset,
        AssetCompletenessService $completenessService
    ): View {

        $this->ensureAssetIsVisible(
            $request->user(),
            $asset
        );


        $asset->load([
            'category',
            'assetType',
            'photos',
            'attributeValues.definition',
            'attributeValues.option',
        ]);


        $categories =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get();


        $types =
            AssetType::query()
                ->where(
                    'company_id',
                    $asset->company_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->with([
                    'attributeDefinitions' =>
                        function ($query) {

                            $query
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->with([
                                    'options' =>
                                        function ($optionQuery) {

                                            $optionQuery
                                                ->where(
                                                    'is_active',
                                                    true
                                                )
                                                ->orderBy(
                                                    'sort_order'
                                                )
                                                ->orderBy('id');
                                        },
                                ])
                                ->orderBy(
                                    'sort_order'
                                )
                                ->orderBy('id');
                        },
                ])
                ->orderBy(
                    'sort_order'
                )
                ->orderBy('name')
                ->get();


        $codingSites =
            Site::withoutGlobalScopes()
                ->where(
                    'company_id',
                    $asset->company_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'name'
                )
                ->get();


        $categoryCodingMapping =
            $asset->asset_category_id !== null
                ? AssetCategoryCodingMapping::withoutGlobalScopes()
                    ->where(
                        'company_id',
                        $asset->company_id
                    )
                    ->where(
                        'asset_category_id',
                        $asset->asset_category_id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->first()
                : null;


        $typeCodingCode =
            $asset->assetType !== null
                ? trim(
                    (string) (
                        $asset->assetType->coding_code
                        ?? ''
                    )
                )
                : '';

        $dynamicValues =
            $asset->attributeValues
                ->keyBy(
                    'asset_attribute_definition_id'
                );


        $completeness =
            $completenessService->check(
                $asset,
                AssetCompletenessService::CONTEXT_ASSET_MANAGER_REVIEW
            );


        return view(
            'asset_completeness.edit',
            compact(
                'asset',
                'categories',
                'types',
                'dynamicValues',
                'completeness',
                'codingSites',
                'categoryCodingMapping',
                'typeCodingCode'
            )
        );
    }


    public function update(
        AssetRequest $request,
        Asset $asset,
        AssetPhotoService $assetPhotoService,
        AssetAttributeValueService $attributeValueService,
        AssetCompletenessService $completenessService
    ): RedirectResponse {

        $this->ensureAssetIsVisible(
            $request->user(),
            $asset
        );


        $data =
            $request->validated();


        unset(
            $data['company_id'],
            $data['photos'],
            $data['dynamic_attributes'],
            $data['dynamic_attribute_files']
        );


        DB::transaction(
            function () use (
                $request,
                $asset,
                $data,
                $assetPhotoService,
                $attributeValueService
            ): void {

                $asset->update(
                    $data
                );


                $assetPhotoService
                    ->storeUploadedPhotos(
                        asset:
                            $asset,

                        files:
                            $request->file(
                                'photos',
                                []
                            ),

                        user:
                            $request->user()
                    );


                $attributeValueService
                    ->sync(
                        $asset,
                        $request,
                        $request->user()
                    );
            }
        );


        $asset->refresh();


        $result =
            $completenessService->check(
                $asset,
                AssetCompletenessService::CONTEXT_ASSET_MANAGER_REVIEW
            );


        if (
            $result['complete']
            ===
            true
        ) {

            return redirect()
                ->route(
                    'asset-completeness.index'
                )
                ->with(
                    'success',
                    'شناسنامه دارایی کامل شد.'
                );
        }


        return redirect()
            ->route(
                'asset-completeness.edit',
                $asset
            )
            ->with(
                'success',
                'اطلاعات ذخیره شد. هنوز مواردی برای تکمیل باقی مانده است.'
            );
    }


    public function issueAssetCode(
        Request $request,
        Asset $asset,
        AssetCodeIssuanceService $issuanceService,
        AssetCompletenessService $completenessService
    ): RedirectResponse {

        $this->ensureAssetIsVisible(
            $request->user(),
            $asset
        );


        if (
            trim(
                (string) (
                    $asset->asset_code
                    ?? ''
                )
            )
            !==
            ''
        ) {

            return redirect()
                ->route(
                    'asset-completeness.edit',
                    $asset
                )
                ->with(
                    'success',
                    'این دارایی قبلاً کد دائمی اموال دریافت کرده است.'
                );
        }


        $validated =
            $request->validate([
                'coding_site_id' => [
                    'required',
                    'integer',
                ],
            ]);


        $completeness =
            $completenessService->check(
                $asset,
                AssetCompletenessService::CONTEXT_ASSET_MANAGER_REVIEW
            );


        if (
            !$completeness['complete']
        ) {

            throw ValidationException::withMessages([
                'asset_code' =>
                    'قبل از صدور کد اموال، شناسنامه مرحله جمعدار اموال را تکمیل کنید: '
                    .
                    implode(
                        '، ',
                        $completeness['missing']
                    ),
            ]);
        }


        $issued =
            $issuanceService->issue(
                asset:
                    $asset,

                codingSiteId:
                    (int) $validated['coding_site_id'],

                actor:
                    $request->user()
            );


        return redirect()
            ->route(
                'asset-completeness.edit',
                $issued
            )
            ->with(
                'success',
                'کد دائمی اموال '
                .
                $issued->asset_code
                .
                ' با موفقیت صادر شد.'
            );
    }

    private function ensureAssetIsVisible(
        User $user,
        Asset $asset
    ): void {

        if (
            $user->isSuperAdmin()
        ) {

            return;
        }


        if (
            $asset->company_id
            ===
            null
            ||
            (int) $asset->company_id
            !==
            (int) $user->company_id
        ) {

            abort(404);
        }
    }
}