<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

use App\Models\Asset;
use App\Models\AssetCodeSequence;
use App\Models\Company;
use App\Services\BulkImport\AssetImportCommitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

final class AssetCodeBulkImportTest extends TestCase
{
    use DatabaseTransactions;


    public function test_bulk_import_creates_asset_without_permanent_code(): void
    {
        $company =
            Company::query()
                ->findOrFail(
                    2
                );


        $beforeSequenceCount =
            AssetCodeSequence::query()
                ->where(
                    'company_id',
                    2
                )
                ->count();


        $rows = [
            [
                'valid' =>
                    true,

                'data' => [
                    'asset_category_id' =>
                        1,

                    'asset_type_id' =>
                        1,

                    'inventory_code' =>
                        'BULK-DEFERRED-CODE',

                    'title' =>
                        'Bulk Deferred Code Test',

                    'brand' =>
                        'TEST',

                    'model' =>
                        'IMPORT',

                    'purchase_price' =>
                        0,

                    'is_active' =>
                        true,
                ],
            ],
        ];


        $request =
            Request::create(
                '/bulk-import/assets/commit',
                'POST'
            );


        $created =
            app(
                AssetImportCommitService::class
            )
                ->commit(
                    company:
                        $company,

                    rows:
                        $rows,

                    request:
                        $request
                );


        self::assertCount(
            1,
            $created
        );


        $asset =
            $created->first();


        self::assertInstanceOf(
            Asset::class,
            $asset
        );


        self::assertSame(
            2,
            (int) $asset->company_id
        );


        self::assertSame(
            1,
            (int) $asset->asset_category_id
        );


        self::assertSame(
            1,
            (int) $asset->asset_type_id
        );


        /*
         * Critical lifecycle assertion:
         *
         * Import does NOT consume a permanent asset code.
         */
        self::assertNull(
            $asset->asset_code
        );


        self::assertSame(
            'warehouse',
            $asset->status
        );


        self::assertNull(
            $asset->plate_number
        );


        $afterSequenceCount =
            AssetCodeSequence::query()
                ->where(
                    'company_id',
                    2
                )
                ->count();


        self::assertSame(
            $beforeSequenceCount,
            $afterSequenceCount
        );
    }


    public function test_multiple_imported_types_do_not_consume_code_sequences(): void
    {
        $company =
            Company::query()
                ->findOrFail(
                    2
                );


        $before =
            AssetCodeSequence::query()
                ->where(
                    'company_id',
                    2
                )
                ->get()
                ->mapWithKeys(
                    fn (AssetCodeSequence $row): array => [
                        $row->prefix =>
                            (int) $row->last_sequence,
                    ]
                )
                ->all();


        $rows = [
            [
                'valid' => true,

                'data' => [
                    'asset_category_id' => 1,
                    'asset_type_id' => 1,
                    'inventory_code' => 'BULK-DEFERRED-LAPTOP',
                    'title' => 'Deferred Laptop',
                    'purchase_price' => 0,
                ],
            ],

            [
                'valid' => true,

                'data' => [
                    'asset_category_id' => 1,
                    'asset_type_id' => 2,
                    'inventory_code' => 'BULK-DEFERRED-MOUSE',
                    'title' => 'Deferred Mouse',
                    'purchase_price' => 0,
                ],
            ],
        ];


        $created =
            app(
                AssetImportCommitService::class
            )
                ->commit(
                    company:
                        $company,

                    rows:
                        $rows,

                    request:
                        Request::create(
                            '/bulk-import/assets/commit',
                            'POST'
                        )
                );


        self::assertCount(
            2,
            $created
        );


        self::assertNull(
            $created[0]->asset_code
        );


        self::assertNull(
            $created[1]->asset_code
        );


        $after =
            AssetCodeSequence::query()
                ->where(
                    'company_id',
                    2
                )
                ->get()
                ->mapWithKeys(
                    fn (AssetCodeSequence $row): array => [
                        $row->prefix =>
                            (int) $row->last_sequence,
                    ]
                )
                ->all();


        self::assertSame(
            $before,
            $after
        );
    }
}