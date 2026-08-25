<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\Asset;
use App\Services\AssetCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodeImmutabilityTest extends TestCase
{
    use DatabaseTransactions;


    public function test_asset_code_cannot_be_changed_after_creation(): void
    {
        $service =
            app(
                AssetCodeService::class
            );

        $code =
            $service->generate(
                companyId: 2,
                assetCategoryId: 1,
                assetTypeId: 1,
            );


        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => 1,
                    'asset_code' => $code,
                    'inventory_code' => null,
                    'title' => 'Immutable Asset Code Test',
                    'purchase_price' => 0,
                    'is_active' => true,
                    'status' => 'warehouse',
                    'plate_number' => null,
                ]);


        self::assertSame(
            'COMPUTER-LAPTOP-000001',
            $asset->asset_code
        );


        $asset->update([
            'asset_code' =>
                'HACKED-999999',
        ]);


        $asset->refresh();


        self::assertSame(
            'COMPUTER-LAPTOP-000001',
            $asset->asset_code
        );


        $this->assertDatabaseMissing(
            'assets',
            [
                'id' => $asset->id,
                'asset_code' =>
                    'HACKED-999999',
            ]
        );
    }


    public function test_other_asset_fields_can_still_be_updated(): void
    {
        $service =
            app(
                AssetCodeService::class
            );

        $code =
            $service->generate(
                companyId: 2,
                assetCategoryId: 1,
                assetTypeId: 1,
            );


        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => 1,
                    'asset_code' => $code,
                    'inventory_code' => null,
                    'title' => 'Old Asset Title',
                    'purchase_price' => 0,
                    'is_active' => true,
                    'status' => 'warehouse',
                    'plate_number' => null,
                ]);


        $asset->update([
            'title' =>
                'Updated Asset Title',

            'asset_code' =>
                'ILLEGAL-CHANGE',
        ]);


        $asset->refresh();


        self::assertSame(
            'Updated Asset Title',
            $asset->title
        );


        self::assertSame(
            'COMPUTER-LAPTOP-000001',
            $asset->asset_code
        );
    }


    public function test_category_or_type_change_does_not_regenerate_asset_code(): void
    {
        $service =
            app(
                AssetCodeService::class
            );

        $code =
            $service->generate(
                companyId: 2,
                assetCategoryId: 1,
                assetTypeId: 1,
            );


        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => 1,
                    'asset_code' => $code,
                    'inventory_code' => null,
                    'title' => 'Classification Change Test',
                    'purchase_price' => 0,
                    'is_active' => true,
                    'status' => 'warehouse',
                    'plate_number' => null,
                ]);


        $originalCode =
            $asset->asset_code;


        /*
         * Change only classification.
         *
         * Asset code must remain a historical stable identifier.
         */
        $asset->update([
            'asset_type_id' => 2,
        ]);


        $asset->refresh();


        self::assertSame(
            2,
            (int) $asset->asset_type_id
        );


        self::assertSame(
            $originalCode,
            $asset->asset_code
        );
    }
}