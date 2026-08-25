<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AssetCode;

use App\Services\AssetCode\AssetCodePolicy;
use App\Services\AssetCode\AssetCodePolicyEngine;
use PHPUnit\Framework\TestCase;

final class AssetCodePolicyEngineTest extends TestCase
{
    public function test_legacy_fallback_policy_is_preserved(): void
    {
        $engine =
            new AssetCodePolicyEngine();

        $policy =
            $engine->buildPolicy(
                categoryCode: null,
                typeCode: null,
            );

        self::assertSame(
            'AST-000001',
            $policy->format(1)
        );

        self::assertSame(
            1,
            $policy->parseSequence(
                'AST-000001'
            )
        );
    }


    public function test_category_only_policy_is_supported(): void
    {
        $engine =
            new AssetCodePolicyEngine();

        $policy =
            $engine->buildPolicy(
                categoryCode: 'COMPUTER',
                typeCode: null,
            );

        self::assertSame(
            'COMPUTER-ASSET-000001',
            $policy->format(1)
        );

        self::assertSame(
            18,
            $policy->parseSequence(
                'COMPUTER-ASSET-000018'
            )
        );
    }


    public function test_semantic_policy_uses_category_and_type(): void
    {
        $engine =
            new AssetCodePolicyEngine();

        $policy =
            $engine->buildPolicy(
                categoryCode: 'COMPUTER',
                typeCode: 'LAPTOP',
            );

        self::assertSame(
            'COMPUTER-LAPTOP-000001',
            $policy->format(1)
        );

        self::assertSame(
            'COMPUTER-LAPTOP-000042',
            $policy->format(42)
        );

        self::assertSame(
            42,
            $policy->parseSequence(
                'COMPUTER-LAPTOP-000042'
            )
        );

        self::assertNull(
            $policy->parseSequence(
                'COMPUTER-MOUSE-000042'
            )
        );
    }


    public function test_codes_are_normalized(): void
    {
        $engine =
            new AssetCodePolicyEngine();

        $policy =
            $engine->buildPolicy(
                categoryCode:
                    ' computer equipment ',

                typeCode:
                    'note book',
            );

        self::assertSame(
            'COMPUTER-EQUIPMENT-NOTE-BOOK-000001',
            $policy->format(1)
        );
    }


    public function test_policy_parser_rejects_other_prefixes(): void
    {
        $policy =
            new AssetCodePolicy(
                prefix:
                    'VEHICLE-CAR',

                padding:
                    6,
            );

        self::assertSame(
            7,
            $policy->parseSequence(
                'VEHICLE-CAR-000007'
            )
        );

        self::assertNull(
            $policy->parseSequence(
                'VEHICLE-TRUCK-000007'
            )
        );
    }
}