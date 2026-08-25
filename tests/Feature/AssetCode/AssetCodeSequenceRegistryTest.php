<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\AssetCodePolicySetting;
use App\Models\AssetCodeSequence;
use App\Services\AssetCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetCodeSequenceRegistryTest extends TestCase
{
    use DatabaseTransactions;


    public function test_sequence_registry_starts_new_family_at_one(): void
    {
        $this->resetCompany(
            2
        );


        $code =
            app(
                AssetCodeService::class
            )
                ->generate(
                    companyId:
                        2
                );


        self::assertSame(
            'AST-000001',
            $code
        );


        $sequence =
            AssetCodeSequence::query()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'prefix',
                    'AST'
                )
                ->firstOrFail();


        self::assertSame(
            1,
            (int) $sequence->last_sequence
        );
    }


    public function test_sequence_registry_increments_without_scanning_assets(): void
    {
        $this->resetCompany(
            2
        );


        AssetCodeSequence::query()
            ->create([
                'company_id' =>
                    2,

                'prefix' =>
                    'AST',

                'last_sequence' =>
                    50,
            ]);


        $code =
            app(
                AssetCodeService::class
            )
                ->generate(
                    companyId:
                        2
                );


        self::assertSame(
            'AST-000051',
            $code
        );


        $this->assertDatabaseHas(
            'asset_code_sequences',
            [
                'company_id' =>
                    2,

                'prefix' =>
                    'AST',

                'last_sequence' =>
                    51,
            ]
        );
    }


    public function test_sequences_are_isolated_per_company(): void
    {
        /*
         * Manual policy-panel settings must not affect
         * this infrastructure test.
         */

        $this->resetCompany(
            1
        );

        $this->resetCompany(
            2
        );


        AssetCodeSequence::query()
            ->create([
                'company_id' =>
                    1,

                'prefix' =>
                    'AST',

                'last_sequence' =>
                    10,
            ]);


        AssetCodeSequence::query()
            ->create([
                'company_id' =>
                    2,

                'prefix' =>
                    'AST',

                'last_sequence' =>
                    20,
            ]);


        $service =
            app(
                AssetCodeService::class
            );


        $companyOne =
            $service->generate(
                companyId:
                    1
            );


        $companyTwo =
            $service->generate(
                companyId:
                    2
            );


        self::assertSame(
            'AST-000011',
            $companyOne
        );


        self::assertSame(
            'AST-000021',
            $companyTwo
        );


        $this->assertDatabaseHas(
            'asset_code_sequences',
            [
                'company_id' =>
                    1,

                'prefix' =>
                    'AST',

                'last_sequence' =>
                    11,
            ]
        );


        $this->assertDatabaseHas(
            'asset_code_sequences',
            [
                'company_id' =>
                    2,

                'prefix' =>
                    'AST',

                'last_sequence' =>
                    21,
            ]
        );
    }


    private function resetCompany(
        int $companyId
    ): void {

        AssetCodePolicySetting::query()
            ->where(
                'company_id',
                $companyId
            )
            ->delete();


        AssetCodeSequence::query()
            ->where(
                'company_id',
                $companyId
            )
            ->where(
                'prefix',
                'AST'
            )
            ->delete();
    }
}