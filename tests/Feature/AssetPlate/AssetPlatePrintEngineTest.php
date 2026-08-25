<?php

declare(strict_types=1);

namespace Tests\Feature\AssetPlate;

use App\Models\Asset;
use App\Models\AssetPlateTemplate;
use App\Services\AssetPlatePrintRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AssetPlatePrintEngineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_print_routes_exist(): void
    {
        self::assertTrue(Route::has('asset-plates.index'));
        self::assertTrue(Route::has('asset-plates.preview'));
        self::assertTrue(Route::has('asset-plates.single'));
        self::assertTrue(
            Route::has('final-warehouse-deliveries.plates.preview')
        );
    }

    public function test_renderer_outputs_real_qr_and_code128_svg_from_asset_code(): void
    {
        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => 2,
            'asset_category_id' => 1,
            'asset_type_id' => 1,
            'asset_code' => '91-92-093-9001',
            'title' => 'Print Engine Test Asset',
            'purchase_price' => 0,
            'status' => 'warehouse',
            'is_active' => true,
        ]);

        $template = AssetPlateTemplate::withoutGlobalScopes()->create([
            'company_id' => 2,
            'name' => 'Print Engine Test Template',
            'width_mm' => 50,
            'height_mm' => 30,
            'orientation' => 'landscape',
            'elements' => [
                [
                    'id' => 'asset_code_text',
                    'type' => 'field',
                    'field' => 'asset_code',
                    'label' => 'کد اموال',
                    'text' => '',
                    'x' => 2, 'y' => 2, 'w' => 30, 'h' => 6,
                    'font_size' => 9,
                    'align' => 'center',
                    'bold' => true,
                ],
                [
                    'id' => 'asset_qr',
                    'type' => 'qr',
                    'field' => 'asset_code',
                    'label' => 'QR',
                    'text' => '',
                    'x' => 2, 'y' => 10, 'w' => 14, 'h' => 14,
                    'font_size' => 9,
                    'align' => 'center',
                    'bold' => false,
                ],
                [
                    'id' => 'asset_barcode',
                    'type' => 'barcode',
                    'field' => 'asset_code',
                    'label' => 'Barcode',
                    'text' => '',
                    'x' => 18, 'y' => 10, 'w' => 28, 'h' => 10,
                    'font_size' => 9,
                    'align' => 'center',
                    'bold' => false,
                ],
            ],
            'is_default' => false,
            'is_active' => true,
        ]);

        $rendered = app(AssetPlatePrintRenderer::class)
            ->render($asset, $template);

        self::assertSame('91-92-093-9001', $rendered['asset_code']);

        $qr = collect($rendered['elements'])->firstWhere('type', 'qr');
        $barcode = collect($rendered['elements'])->firstWhere('type', 'barcode');

        self::assertIsArray($qr);
        self::assertIsArray($barcode);
        self::assertStringContainsString('<svg', (string) $qr['svg']);
        self::assertStringContainsString('<svg', (string) $barcode['svg']);
        self::assertSame('91-92-093-9001', $qr['value']);
        self::assertSame('91-92-093-9001', $barcode['value']);
    }

    public function test_renderer_rejects_asset_without_permanent_code(): void
    {
        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => 2,
            'asset_category_id' => 1,
            'asset_type_id' => 1,
            'asset_code' => null,
            'title' => 'Asset Without Code',
            'purchase_price' => 0,
            'status' => 'warehouse',
            'is_active' => true,
        ]);

        /*
         * Each PHPUnit test runs in its own database transaction.
         * Do not depend on the template created by another test.
         */
        $template = AssetPlateTemplate::withoutGlobalScopes()->create([
            'company_id' => 2,
            'name' => 'Reject Missing Code Template',
            'width_mm' => 50,
            'height_mm' => 30,
            'orientation' => 'landscape',
            'elements' => [
                [
                    'id' => 'asset_code_text',
                    'type' => 'field',
                    'field' => 'asset_code',
                    'label' => 'کد اموال',
                    'text' => '',
                    'x' => 2,
                    'y' => 2,
                    'w' => 30,
                    'h' => 6,
                    'font_size' => 9,
                    'align' => 'center',
                    'bold' => true,
                ],
            ],
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);

        app(AssetPlatePrintRenderer::class)
            ->render($asset, $template);
    }
}