<?php

declare(strict_types=1);

namespace Tests\Feature\AssetPlate;

use App\Models\AssetPlateTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AssetPlateTemplateFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_plate_template_can_store_company_specific_size_and_layout(): void
    {
        $template = AssetPlateTemplate::query()->create([
            'company_id' => 2,
            'name' => 'Test 50x30',
            'width_mm' => 50,
            'height_mm' => 30,
            'orientation' => 'landscape',
            'elements' => AssetPlateTemplate::defaultElements(),
            'is_default' => true,
            'is_active' => true,
        ]);

        self::assertSame(
            2,
            $template->company_id
        );

        self::assertSame(
            '50.00',
            (string) $template->width_mm
        );

        self::assertSame(
            '30.00',
            (string) $template->height_mm
        );

        self::assertIsArray(
            $template->elements
        );

        self::assertNotEmpty(
            $template->elements
        );
    }

    public function test_plate_designer_routes_exist(): void
    {
        self::assertTrue(
            Route::has(
                'asset-settings.plate-templates.index'
            )
        );

        self::assertTrue(
            Route::has(
                'asset-settings.plate-templates.create'
            )
        );

        self::assertTrue(
            Route::has(
                'asset-settings.plate-templates.store'
            )
        );

        self::assertTrue(
            Route::has(
                'asset-settings.plate-templates.edit'
            )
        );

        self::assertTrue(
            Route::has(
                'asset-settings.plate-templates.update'
            )
        );
    }

    public function test_default_layout_contains_real_asset_fields_and_qr(): void
    {
        $elements =
            AssetPlateTemplate::defaultElements();

        $fields =
            collect($elements)
                ->pluck('field')
                ->filter()
                ->values()
                ->all();

        $types =
            collect($elements)
                ->pluck('type')
                ->values()
                ->all();

        self::assertContains(
            'asset_code',
            $fields
        );

        $assetCodeTextFields =
            collect($elements)
                ->filter(
                    static fn (array $element): bool =>
                        ($element['type'] ?? null) === 'field'
                        &&
                        ($element['field'] ?? null) === 'asset_code'
                );

        self::assertCount(
            1,
            $assetCodeTextFields
        );

        $assetCodeQrElements =
            collect($elements)
                ->filter(
                    static fn (array $element): bool =>
                        ($element['type'] ?? null) === 'qr'
                        &&
                        ($element['field'] ?? null) === 'asset_code'
                );

        self::assertCount(
            1,
            $assetCodeQrElements
        );

        self::assertNotContains(
            'plate_number',
            $fields
        );

        self::assertContains(
            'qr',
            $types
        );
    }
}
