<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodeMasterDataPanelFoundationTest extends TestCase
{
    use DatabaseTransactions;


    public function test_category_coding_mapping_is_company_specific(): void
    {
        AssetCategoryCodingMapping::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' =>
                        1,

                    'asset_category_id' =>
                        1,
                ],
                [
                    'coding_code' =>
                        '06',

                    'is_active' =>
                        true,
                ]
            );


        AssetCategoryCodingMapping::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' =>
                        2,

                    'asset_category_id' =>
                        1,
                ],
                [
                    'coding_code' =>
                        '03',

                    'is_active' =>
                        true,
                ]
            );


        self::assertSame(
            '06',
            AssetCategoryCodingMapping::withoutGlobalScopes()
                ->where(
                    'company_id',
                    1
                )
                ->where(
                    'asset_category_id',
                    1
                )
                ->value(
                    'coding_code'
                )
        );


        self::assertSame(
            '03',
            AssetCategoryCodingMapping::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_category_id',
                    1
                )
                ->value(
                    'coding_code'
                )
        );
    }


    public function test_asset_type_keeps_operational_code_and_separate_coding_code(): void
    {
        $type =
            AssetType::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'code',
                    'LAPTOP'
                )
                ->firstOrFail();


        $operationalCode =
            $type->code;


        $type->coding_code =
            '012';

        $type->save();

        $type->refresh();


        self::assertSame(
            $operationalCode,
            $type->code
        );


        self::assertSame(
            '012',
            $type->coding_code
        );
    }
}