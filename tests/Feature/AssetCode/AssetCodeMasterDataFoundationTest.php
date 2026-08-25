<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodeMasterDataFoundationTest extends TestCase
{
    use DatabaseTransactions;


    public function test_existing_site_master_data_provides_site_code(): void
    {
        $site =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'name' =>
                        'Shokouhieh',

                    'code' =>
                        '02',

                    'type' =>
                        'factory',

                    'is_active' =>
                        true,
                ]);


        self::assertSame(
            '02',
            $site->code
        );


        self::assertSame(
            'Shokouhieh',
            $site->name
        );
    }


    public function test_existing_category_can_have_separate_coding_code(): void
    {
        $category =
            AssetCategory::query()
                ->findOrFail(
                    1
                );


        $category->coding_code =
            '06';

        $category->save();


        $category->refresh();


        /*
         * Existing operational code remains untouched.
         */
        self::assertSame(
            'COMPUTER',
            $category->code
        );


        /*
         * Coding engine can independently use 06.
         */
        self::assertSame(
            '06',
            $category->coding_code
        );
    }


    public function test_existing_asset_type_can_have_separate_coding_code(): void
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


        $type->coding_code =
            '012';

        $type->save();


        $type->refresh();


        self::assertSame(
            'LAPTOP',
            $type->code
        );


        self::assertSame(
            '012',
            $type->coding_code
        );
    }


    public function test_final_segments_can_come_only_from_existing_master_data(): void
    {
        $site =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'name' => 'Shokouhieh',
                    'code' => '02',
                    'type' => 'factory',
                    'is_active' => true,
                ]);


        $category =
            AssetCategory::query()
                ->findOrFail(
                    1
                );


        $category->coding_code =
            '06';

        $category->save();


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


        $type->coding_code =
            '012';

        $type->save();


        $code =
            implode(
                '-',
                [
                    $site->code,
                    $category->coding_code,
                    $type->coding_code,
                    '0001',
                ]
            );


        self::assertSame(
            '02-06-012-0001',
            $code
        );
    }
}