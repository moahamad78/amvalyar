<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\AssetCodePolicySetting;
use App\Services\AssetCode\AssetCodePolicyEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodePolicySettingsTest extends TestCase
{
    use DatabaseTransactions;


    public function test_default_settings_preserve_current_semantic_format(): void
    {
        AssetCodePolicySetting::query()
            ->where(
                'company_id',
                2
            )
            ->delete();


        $engine =
            app(
                AssetCodePolicyEngine::class
            );


        $policy =
            $engine->resolve(
                companyId:
                    2,

                assetCategoryId:
                    1,

                assetTypeId:
                    1,
            );


        self::assertSame(
            'COMPUTER-LAPTOP-000001',
            $policy->format(
                1
            )
        );
    }


    public function test_company_can_switch_to_legacy_format(): void
    {
        AssetCodePolicySetting::query()
            ->updateOrCreate(
                [
                    'company_id' =>
                        2,
                ],
                [
                    'mode' =>
                        'legacy',

                    'fallback_prefix' =>
                        'FIXED',

                    'padding' =>
                        4,

                    'separator' =>
                        '-',

                    'include_category' =>
                        true,

                    'include_type' =>
                        true,
                ]
            );


        $engine =
            app(
                AssetCodePolicyEngine::class
            );


        $policy =
            $engine->resolve(
                companyId:
                    2,

                assetCategoryId:
                    1,

                assetTypeId:
                    1,
            );


        self::assertSame(
            'FIXED-0001',
            $policy->format(
                1
            )
        );
    }


    public function test_company_can_use_category_only_policy(): void
    {
        AssetCodePolicySetting::query()
            ->updateOrCreate(
                [
                    'company_id' =>
                        2,
                ],
                [
                    'mode' =>
                        'semantic',

                    'fallback_prefix' =>
                        'AST',

                    'padding' =>
                        5,

                    'separator' =>
                        '_',

                    'include_category' =>
                        true,

                    'include_type' =>
                        false,
                ]
            );


        $engine =
            app(
                AssetCodePolicyEngine::class
            );


        $policy =
            $engine->resolve(
                companyId:
                    2,

                assetCategoryId:
                    1,

                assetTypeId:
                    1,
            );


        self::assertSame(
            'COMPUTER_00001',
            $policy->format(
                1
            )
        );
    }


    public function test_company_can_use_type_only_policy(): void
    {
        AssetCodePolicySetting::query()
            ->updateOrCreate(
                [
                    'company_id' =>
                        2,
                ],
                [
                    'mode' =>
                        'semantic',

                    'fallback_prefix' =>
                        'AST',

                    'padding' =>
                        6,

                    'separator' =>
                        '.',

                    'include_category' =>
                        false,

                    'include_type' =>
                        true,
                ]
            );


        $engine =
            app(
                AssetCodePolicyEngine::class
            );


        $policy =
            $engine->resolve(
                companyId:
                    2,

                assetCategoryId:
                    1,

                assetTypeId:
                    1,
            );


        self::assertSame(
            'LAPTOP.000001',
            $policy->format(
                1
            )
        );
    }
}