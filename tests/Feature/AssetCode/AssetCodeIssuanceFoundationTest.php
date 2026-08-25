<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\Asset;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCodeSequence;
use App\Models\AssetType;
use App\Models\Site;
use App\Services\AssetCode\AssetCodeIssuanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodeIssuanceFoundationTest extends TestCase
{
    use DatabaseTransactions;


    public function test_permanent_code_is_built_from_existing_master_data_codes(): void
    {
        $site =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'name' => 'Shokouhieh Test',
                    'code' => '02',
                    'type' => 'factory',
                    'is_active' => true,
                ]);


        AssetCategoryCodingMapping::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' => 2,
                    'asset_category_id' => 1,
                ],
                [
                    'coding_code' => '06',
                    'is_active' => true,
                ]
            );


        $type =
            AssetType::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_category_id',
                    1
                )
                ->where(
                    'code',
                    'LAPTOP'
                )
                ->firstOrFail();


        $type->coding_code =
            '012';

        $type->save();


        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => $type->id,
                    'asset_code' => null,
                    'title' => 'Permanent Code Issuance Test',
                    'purchase_price' => 0,
                    'status' => 'warehouse',
                    'is_active' => true,
                    'plate_number' => null,
                ]);


        $issued =
            app(
                AssetCodeIssuanceService::class
            )
                ->issue(
                    asset:
                        $asset,

                    codingSiteId:
                        $site->id,

                    actor:
                        null,

                    separator:
                        '-',

                    serialPadding:
                        4
                );


        self::assertSame(
            '02-06-012-0001',
            $issued->asset_code
        );


        self::assertSame(
            '02',
            $issued->coding_site_code_snapshot
        );


        self::assertSame(
            '06',
            $issued->main_nature_code_snapshot
        );


        self::assertSame(
            '012',
            $issued->sub_nature_code_snapshot
        );


        self::assertSame(
            $site->id,
            $issued->coding_site_id
        );


        self::assertNotNull(
            $issued->asset_code_issued_at
        );
    }


    public function test_same_family_gets_next_serial(): void
    {
        $site =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'name' => 'Sequence Site',
                    'code' => '91',
                    'type' => 'factory',
                    'is_active' => true,
                ]);


        AssetCategoryCodingMapping::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' => 2,
                    'asset_category_id' => 1,
                ],
                [
                    'coding_code' => '81',
                    'is_active' => true,
                ]
            );


        $type =
            AssetType::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_category_id',
                    1
                )
                ->where(
                    'code',
                    'LAPTOP'
                )
                ->firstOrFail();


        $type->coding_code =
            '071';

        $type->save();


        $service =
            app(
                AssetCodeIssuanceService::class
            );


        $first =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => $type->id,
                    'asset_code' => null,
                    'title' => 'Sequence Test 1',
                    'purchase_price' => 0,
                    'status' => 'warehouse',
                    'is_active' => true,
                ]);


        $second =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => $type->id,
                    'asset_code' => null,
                    'title' => 'Sequence Test 2',
                    'purchase_price' => 0,
                    'status' => 'warehouse',
                    'is_active' => true,
                ]);


        $first =
            $service->issue(
                $first,
                $site->id
            );


        $second =
            $service->issue(
                $second,
                $site->id
            );


        self::assertSame(
            '91-81-071-0001',
            $first->asset_code
        );


        self::assertSame(
            '91-81-071-0002',
            $second->asset_code
        );
    }


    public function test_issued_code_cannot_be_changed_later(): void
    {
        $site =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'name' => 'Immutable Site',
                    'code' => '92',
                    'type' => 'factory',
                    'is_active' => true,
                ]);


        AssetCategoryCodingMapping::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' => 2,
                    'asset_category_id' => 1,
                ],
                [
                    'coding_code' => '82',
                    'is_active' => true,
                ]
            );


        $type =
            AssetType::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_category_id',
                    1
                )
                ->where(
                    'code',
                    'LAPTOP'
                )
                ->firstOrFail();


        $type->coding_code =
            '072';

        $type->save();


        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => $type->id,
                    'asset_code' => null,
                    'title' => 'Immutable Permanent Code',
                    'purchase_price' => 0,
                    'status' => 'warehouse',
                    'is_active' => true,
                ]);


        $asset =
            app(
                AssetCodeIssuanceService::class
            )
                ->issue(
                    $asset,
                    $site->id
                );


        $original =
            $asset->asset_code;


        $asset->asset_code =
            'CHANGED-9999';

        $asset->save();

        $asset->refresh();


        self::assertSame(
            $original,
            $asset->asset_code
        );
    }


    public function test_current_site_may_change_without_changing_coding_site_or_asset_code(): void
    {
        $codingSite =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'name' => 'Coding Site',
                    'code' => '93',
                    'type' => 'factory',
                    'is_active' => true,
                ]);


        $futureSite =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'name' => 'Future Current Site',
                    'code' => '94',
                    'type' => 'factory',
                    'is_active' => true,
                ]);


        AssetCategoryCodingMapping::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' => 2,
                    'asset_category_id' => 1,
                ],
                [
                    'coding_code' => '83',
                    'is_active' => true,
                ]
            );


        $type =
            AssetType::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_category_id',
                    1
                )
                ->where(
                    'code',
                    'LAPTOP'
                )
                ->firstOrFail();


        $type->coding_code =
            '073';

        $type->save();


        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => $type->id,
                    'asset_code' => null,
                    'title' => 'Site Movement Code Test',
                    'purchase_price' => 0,
                    'status' => 'warehouse',
                    'is_active' => true,
                ]);


        $asset =
            app(
                AssetCodeIssuanceService::class
            )
                ->issue(
                    $asset,
                    $codingSite->id
                );


        $permanentCode =
            $asset->asset_code;


        $asset->current_site_id =
            $futureSite->id;

        $asset->save();

        $asset->refresh();


        self::assertSame(
            $futureSite->id,
            $asset->current_site_id
        );


        self::assertSame(
            $codingSite->id,
            $asset->coding_site_id
        );


        self::assertSame(
            $permanentCode,
            $asset->asset_code
        );
    }
}