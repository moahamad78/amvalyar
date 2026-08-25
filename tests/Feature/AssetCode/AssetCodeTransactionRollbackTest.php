<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\Asset;
use App\Models\AssetCodeSequence;
use App\Services\AssetCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class AssetCodeTransactionRollbackTest extends TestCase
{
    use DatabaseTransactions;


    public function test_sequence_increment_rolls_back_when_outer_transaction_fails(): void
    {
        AssetCodeSequence::query()
            ->updateOrCreate(
                [
                    'company_id' => 2,
                    'prefix' => 'COMPUTER-LAPTOP',
                ],
                [
                    'last_sequence' => 50,
                ]
            );


        $service =
            app(
                AssetCodeService::class
            );


        try {

            DB::transaction(
                function () use ($service): void {

                    $code =
                        $service->generate(
                            companyId: 2,
                            assetCategoryId: 1,
                            assetTypeId: 1,
                        );


                    self::assertSame(
                        'COMPUTER-LAPTOP-000051',
                        $code
                    );


                    /*
                     * Simulate a failure AFTER code allocation.
                     */
                    throw new RuntimeException(
                        'Simulated asset creation failure.'
                    );
                }
            );

            self::fail(
                'Expected RuntimeException was not thrown.'
            );

        } catch (RuntimeException $exception) {

            self::assertSame(
                'Simulated asset creation failure.',
                $exception->getMessage()
            );
        }


        $sequence =
            AssetCodeSequence::query()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'prefix',
                    'COMPUTER-LAPTOP'
                )
                ->firstOrFail();


        self::assertSame(
            50,
            (int) $sequence->last_sequence
        );


        /*
         * The next real generation must reuse 51.
         */
        $nextCode =
            $service->generate(
                companyId: 2,
                assetCategoryId: 1,
                assetTypeId: 1,
            );


        self::assertSame(
            'COMPUTER-LAPTOP-000051',
            $nextCode
        );
    }


    public function test_asset_and_sequence_both_roll_back_together(): void
    {
        AssetCodeSequence::query()
            ->updateOrCreate(
                [
                    'company_id' => 2,
                    'prefix' => 'COMPUTER-MOUSE',
                ],
                [
                    'last_sequence' => 10,
                ]
            );


        $service =
            app(
                AssetCodeService::class
            );


        try {

            DB::transaction(
                function () use ($service): void {

                    $code =
                        $service->generate(
                            companyId: 2,
                            assetCategoryId: 1,
                            assetTypeId: 2,
                        );


                    $asset =
                        Asset::withoutGlobalScopes()
                            ->create([
                                'company_id' => 2,
                                'asset_category_id' => 1,
                                'asset_type_id' => 2,
                                'asset_code' => $code,
                                'inventory_code' => 'ROLLBACK-MOUSE',
                                'title' => 'Rollback Mouse Test',
                                'purchase_price' => 0,
                                'is_active' => true,
                                'status' => 'warehouse',
                                'plate_number' => null,
                            ]);


                    self::assertNotNull(
                        $asset->id
                    );


                    throw new RuntimeException(
                        'Simulated failure after asset insert.'
                    );
                }
            );

            self::fail(
                'Expected RuntimeException was not thrown.'
            );

        } catch (RuntimeException $exception) {

            self::assertSame(
                'Simulated failure after asset insert.',
                $exception->getMessage()
            );
        }


        $this->assertDatabaseMissing(
            'assets',
            [
                'company_id' => 2,
                'inventory_code' => 'ROLLBACK-MOUSE',
            ]
        );


        $sequence =
            AssetCodeSequence::query()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'prefix',
                    'COMPUTER-MOUSE'
                )
                ->firstOrFail();


        self::assertSame(
            10,
            (int) $sequence->last_sequence
        );
    }
}