<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\AssetCodeFormulaSetting;
use App\Services\AssetCode\AssetCodeFormulaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodeFormulaDesignerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_default_formula_matches_target_structure(): void
    {
        $settings = AssetCodeFormulaSetting::defaultsFor(2);

        $code = app(AssetCodeFormulaService::class)->buildCode(
            $settings,
            '02',
            '06',
            '012',
            1
        );

        self::assertSame('02-06-012-0001', $code);
    }

    public function test_segment_order_is_configurable(): void
    {
        $settings = AssetCodeFormulaSetting::defaultsFor(2);
        $settings->segment_order = ['category', 'site', 'type', 'serial'];

        $code = app(AssetCodeFormulaService::class)->buildCode(
            $settings,
            '02',
            '06',
            '012',
            7
        );

        self::assertSame('06-02-012-0007', $code);
    }

    public function test_separator_and_serial_length_are_configurable(): void
    {
        $settings = AssetCodeFormulaSetting::defaultsFor(2);
        $settings->separator = '.';
        $settings->serial_length = 6;

        $code = app(AssetCodeFormulaService::class)->buildCode(
            $settings,
            '02',
            '06',
            '012',
            9
        );

        self::assertSame('02.06.012.000009', $code);
    }

    public function test_sequence_scope_is_configurable(): void
    {
        $service = app(AssetCodeFormulaService::class);
        $settings = AssetCodeFormulaSetting::defaultsFor(2);

        self::assertSame(
            '02-06-012',
            $service->sequenceKey($settings, '02', '06', '012')
        );

        $settings->sequence_scope = 'site';
        self::assertSame(
            '@FORMULA:SITE:02',
            $service->sequenceKey($settings, '02', '06', '012')
        );

        $settings->sequence_scope = 'category';
        self::assertSame(
            '@FORMULA:CATEGORY:06',
            $service->sequenceKey($settings, '02', '06', '012')
        );

        $settings->sequence_scope = 'company';
        self::assertSame(
            '@FORMULA:COMPANY',
            $service->sequenceKey($settings, '02', '06', '012')
        );
    }
}
