<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AssetCodeSettingsConsolidationTest extends TestCase
{
    public function test_unified_asset_code_settings_route_exists(): void
    {
        self::assertTrue(
            Route::has('asset-settings.code.index')
        );
    }

    public function test_legacy_and_specialized_routes_are_preserved(): void
    {
        self::assertTrue(Route::has('asset-settings.code-policy.index'));
        self::assertTrue(Route::has('asset-settings.code-master-data.index'));
        self::assertTrue(Route::has('asset-settings.code-formula.index'));
    }

    public function test_navigation_exposes_only_one_asset_code_settings_entry(): void
    {
        $source = file_get_contents(
            app_path('Services/NavigationService.php')
        );

        self::assertIsString($source);

        self::assertSame(
            1,
            substr_count(
                $source,
                "'asset-settings.code.index'"
            )
        );

        self::assertSame(
            0,
            substr_count(
                $source,
                "'asset-settings.code-master-data.index'"
            )
        );

        self::assertSame(
            0,
            substr_count(
                $source,
                "'asset-settings.code-formula.index'"
            )
        );
    }
}
