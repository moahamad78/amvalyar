<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\Asset;
use App\Services\AssetCompletenessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodeWorkflowLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_asset_manager_review_does_not_require_asset_code_before_issuance(): void
    {
        $asset = $this->asset(null);

        $result =
            app(AssetCompletenessService::class)
                ->check(
                    $asset,
                    AssetCompletenessService::CONTEXT_ASSET_MANAGER_REVIEW
                );

        self::assertArrayNotHasKey(
            'asset_code',
            $result['checks']
        );

        self::assertArrayNotHasKey(
            'plate_number',
            $result['checks']
        );
    }

    public function test_before_delivery_requires_only_permanent_asset_code(): void
    {
        $asset = $this->asset(null);

        $result =
            app(AssetCompletenessService::class)
                ->check(
                    $asset,
                    AssetCompletenessService::CONTEXT_BEFORE_DELIVERY
                );

        self::assertArrayHasKey(
            'asset_code',
            $result['checks']
        );

        self::assertArrayNotHasKey(
            'plate_number',
            $result['checks']
        );

        self::assertTrue(
            $result['checks']['asset_code']['required']
        );

        self::assertFalse(
            $result['checks']['asset_code']['complete']
        );

        self::assertContains(
            'کد دائمی اموال',
            $result['missing']
        );
    }

    public function test_asset_code_is_the_physical_plate_identifier(): void
    {
        $asset =
            $this->asset(
                '99-99-999-9999'
            );

        self::assertSame(
            '99-99-999-9999',
            $asset->asset_code
        );

        self::assertSame(
            $asset->asset_code,
            $asset->plate_number
        );

        $asset->plate_number =
            'PL-DO-NOT-PERSIST';

        $asset->save();
        $asset->refresh();

        self::assertSame(
            '99-99-999-9999',
            $asset->asset_code
        );

        self::assertSame(
            $asset->asset_code,
            $asset->plate_number
        );
    }

    private function asset(
        ?string $assetCode
    ): Asset {
        return Asset::withoutGlobalScopes()
            ->create([
                'company_id' =>
                    2,

                'asset_category_id' =>
                    1,

                'asset_type_id' =>
                    1,

                'asset_code' =>
                    $assetCode,

                'title' =>
                    'Unified Asset Identity Test',

                'purchase_price' =>
                    0,

                'status' =>
                    'warehouse',

                'is_active' =>
                    true,
            ]);
    }
}
