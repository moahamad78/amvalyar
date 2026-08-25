<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\Asset;
use App\Services\AssetCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodeIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_semantic_asset_code_is_saved_on_real_asset(): void
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

        self::assertSame(
            'COMPUTER-LAPTOP-000001',
            $code
        );

        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => 1,
                    'asset_code' => $code,
                    'inventory_code' => null,
                    'title' => 'Asset Code Integration Test',
                    'brand' => 'TEST',
                    'model' => 'TEST',
                    'serial_number' => null,
                    'manufacturer' => null,
                    'country' => null,
                    'purchase_date' => null,
                    'purchase_price' => 0,
                    'description' => null,
                    'is_active' => true,
                    'status' => 'warehouse',
                    'plate_number' => null,
                ]);

        self::assertNotNull(
            $asset->id
        );

        self::assertSame(
            'COMPUTER-LAPTOP-000001',
            $asset->asset_code
        );

        $this->assertDatabaseHas(
            'assets',
            [
                'id' => $asset->id,
                'company_id' => 2,
                'asset_category_id' => 1,
                'asset_type_id' => 1,
                'asset_code' => 'COMPUTER-LAPTOP-000001',
            ]
        );
    }


    public function test_second_asset_in_same_family_gets_next_sequence(): void
    {
        $service =
            app(
                AssetCodeService::class
            );

        $firstCode =
            $service->generate(
                companyId: 2,
                assetCategoryId: 1,
                assetTypeId: 1,
            );

        Asset::withoutGlobalScopes()
            ->create([
                'company_id' => 2,
                'asset_category_id' => 1,
                'asset_type_id' => 1,
                'asset_code' => $firstCode,
                'inventory_code' => null,
                'title' => 'Sequence Test 1',
                'is_active' => true,
                'status' => 'warehouse',
                'plate_number' => null,
            ]);

        $secondCode =
            $service->generate(
                companyId: 2,
                assetCategoryId: 1,
                assetTypeId: 1,
            );

        self::assertSame(
            'COMPUTER-LAPTOP-000002',
            $secondCode
        );
    }


    public function test_different_asset_types_have_independent_sequences(): void
    {
        $service =
            app(
                AssetCodeService::class
            );

        $laptopCode =
            $service->generate(
                companyId: 2,
                assetCategoryId: 1,
                assetTypeId: 1,
            );

        $mouseCode =
            $service->generate(
                companyId: 2,
                assetCategoryId: 1,
                assetTypeId: 2,
            );

        self::assertSame(
            'COMPUTER-LAPTOP-000001',
            $laptopCode
        );

        self::assertSame(
            'COMPUTER-MOUSE-000001',
            $mouseCode
        );
    }
}