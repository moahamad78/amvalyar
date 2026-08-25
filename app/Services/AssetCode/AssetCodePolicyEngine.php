<?php

declare(strict_types=1);

namespace App\Services\AssetCode;

use App\Models\AssetCategory;
use App\Models\AssetCodePolicySetting;
use App\Models\AssetType;
use InvalidArgumentException;
use RuntimeException;

final class AssetCodePolicyEngine
{
    public function resolve(
        int $companyId,
        ?int $assetCategoryId = null,
        ?int $assetTypeId = null,
    ): AssetCodePolicy {

        if ($companyId <= 0) {
            throw new InvalidArgumentException(
                'Company ID must be greater than zero.'
            );
        }


        $settings =
            AssetCodePolicySetting::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->first()
            ??
            AssetCodePolicySetting::defaultsFor(
                $companyId
            );


        if ($assetCategoryId === null) {

            return $this->buildPolicy(
                categoryCode:
                    null,

                typeCode:
                    null,

                settings:
                    $settings,
            );
        }


        $category =
            AssetCategory::query()
                ->whereKey(
                    $assetCategoryId
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();


        if ($category === null) {
            throw new RuntimeException(
                'Active asset category was not found.'
            );
        }


        if ($assetTypeId === null) {

            return $this->buildPolicy(
                categoryCode:
                    (string) $category->code,

                typeCode:
                    null,

                settings:
                    $settings,
            );
        }


        $type =
            AssetType::query()
                ->whereKey(
                    $assetTypeId
                )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'asset_category_id',
                    $assetCategoryId
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();


        if ($type === null) {
            throw new RuntimeException(
                'Active asset type was not found for the selected company and category.'
            );
        }


        return $this->buildPolicy(
            categoryCode:
                (string) $category->code,

            typeCode:
                (string) $type->code,

            settings:
                $settings,
        );
    }


    public function buildPolicy(
        ?string $categoryCode,
        ?string $typeCode,
        ?AssetCodePolicySetting $settings = null,
    ): AssetCodePolicy {

        $settings ??=
            AssetCodePolicySetting::defaultsFor(
                1
            );


        $fallback =
            $this->normalizeSegment(
                $settings->fallback_prefix
            )
            ??
            'AST';


        $separator =
            in_array(
                $settings->separator,
                [
                    '-',
                    '_',
                    '/',
                    '.',
                ],
                true
            )
                ? $settings->separator
                : '-';


        $padding =
            max(
                1,
                min(
                    12,
                    (int) $settings->padding
                )
            );


        /*
         * Legacy mode intentionally ignores category/type.
         */
        if (
            $settings->mode
            ===
            AssetCodePolicySetting::MODE_LEGACY
        ) {

            return new AssetCodePolicy(
                prefix:
                    $fallback,

                padding:
                    $padding,

                separator:
                    $separator,
            );
        }


        $category =
            $this->normalizeSegment(
                $categoryCode
            );


        $type =
            $this->normalizeSegment(
                $typeCode
            );


        $segments = [];


        if (
            $settings->include_category
            &&
            $category !== null
        ) {
            $segments[] =
                $category;
        }


        if ($settings->include_type) {

            if ($type !== null) {

                $segments[] =
                    $type;

            } elseif (
                $category !== null
                &&
                $settings->include_category
            ) {

                /*
                 * Preserve current category-only behavior:
                 *
                 * COMPUTER-ASSET-000001
                 */
                $segments[] =
                    'ASSET';
            }
        }


        if ($segments === []) {

            return new AssetCodePolicy(
                prefix:
                    $fallback,

                padding:
                    $padding,

                separator:
                    $separator,
            );
        }


        return new AssetCodePolicy(
            prefix:
                implode(
                    $separator,
                    $segments
                ),

            padding:
                $padding,

            separator:
                $separator,
        );
    }


    private function normalizeSegment(
        ?string $value
    ): ?string {

        if ($value === null) {
            return null;
        }


        $value =
            strtoupper(
                trim(
                    $value
                )
            );


        if ($value === '') {
            return null;
        }


        $value =
            preg_replace(
                '/[^A-Z0-9]+/',
                '-',
                $value
            );


        if ($value === null) {
            return null;
        }


        $value =
            trim(
                $value,
                '-'
            );


        return $value !== ''
            ? $value
            : null;
    }
}