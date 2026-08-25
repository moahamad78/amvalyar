<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | coding_code columns
        |--------------------------------------------------------------------------
        |
        | These may already exist because a previous SQLite migration attempt
        | partially completed before failing.
        |
        */

        if (
            !Schema::hasColumn(
                'asset_categories',
                'coding_code'
            )
        ) {

            Schema::table(
                'asset_categories',
                function (Blueprint $table): void {

                    $table->string(
                        'coding_code',
                        30
                    )
                        ->nullable()
                        ->after(
                            'code'
                        );
                }
            );
        }


        if (
            !Schema::hasColumn(
                'asset_types',
                'coding_code'
            )
        ) {

            Schema::table(
                'asset_types',
                function (Blueprint $table): void {

                    $table->string(
                        'coding_code',
                        30
                    )
                        ->nullable()
                        ->after(
                            'code'
                        );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Remove obsolete composite index FIRST
        |--------------------------------------------------------------------------
        |
        | SQLite cannot drop one of the indexed columns while this index exists.
        |
        */

        $indexes =
            collect(
                DB::select(
                    "PRAGMA index_list('assets')"
                )
            )
                ->pluck(
                    'name'
                )
                ->all();


        if (
            in_array(
                'assets_company_code_natures_idx',
                $indexes,
                true
            )
        ) {

            DB::statement(
                'DROP INDEX "assets_company_code_natures_idx"'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Remove obsolete foreign-key-backed columns
        |--------------------------------------------------------------------------
        |
        | The source of coding classification will use existing master data.
        |
        */

        if (
            Schema::hasColumn(
                'assets',
                'code_sub_nature_id'
            )
        ) {

            Schema::table(
                'assets',
                function (Blueprint $table): void {

                    $table->dropForeign([
                        'code_sub_nature_id',
                    ]);
                }
            );


            Schema::table(
                'assets',
                function (Blueprint $table): void {

                    $table->dropColumn(
                        'code_sub_nature_id'
                    );
                }
            );
        }


        if (
            Schema::hasColumn(
                'assets',
                'code_main_nature_id'
            )
        ) {

            Schema::table(
                'assets',
                function (Blueprint $table): void {

                    $table->dropForeign([
                        'code_main_nature_id',
                    ]);
                }
            );


            Schema::table(
                'assets',
                function (Blueprint $table): void {

                    $table->dropColumn(
                        'code_main_nature_id'
                    );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Remove duplicate dictionary tables
        |--------------------------------------------------------------------------
        */

        Schema::dropIfExists(
            'asset_code_sub_natures'
        );

        Schema::dropIfExists(
            'asset_code_main_natures'
        );


        /*
        |--------------------------------------------------------------------------
        | Keep permanent issuance context
        |--------------------------------------------------------------------------
        |
        | coding_site_id is intentionally preserved.
        |
        | current_site_id = current physical position
        | coding_site_id  = site used when permanent asset code was issued
        |
        */

        if (
            !Schema::hasColumn(
                'assets',
                'coding_site_id'
            )
        ) {

            Schema::table(
                'assets',
                function (Blueprint $table): void {

                    $table->foreignId(
                        'coding_site_id'
                    )
                        ->nullable()
                        ->constrained(
                            'sites'
                        )
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }
            );
        }


        foreach ([
            'coding_site_code_snapshot' => 50,
            'main_nature_code_snapshot' => 30,
            'sub_nature_code_snapshot' => 30,
        ] as $column => $length) {

            if (
                !Schema::hasColumn(
                    'assets',
                    $column
                )
            ) {

                Schema::table(
                    'assets',
                    function (Blueprint $table) use (
                        $column,
                        $length
                    ): void {

                        $table->string(
                            $column,
                            $length
                        )
                            ->nullable();
                    }
                );
            }
        }
    }


    public function down(): void
    {
        /*
         * Intentionally conservative.
         *
         * This migration repairs a partially-applied development schema.
         * We do not recreate the abandoned duplicate classification tables.
         */

        if (
            Schema::hasColumn(
                'asset_types',
                'coding_code'
            )
        ) {

            Schema::table(
                'asset_types',
                function (Blueprint $table): void {

                    $table->dropColumn(
                        'coding_code'
                    );
                }
            );
        }


        if (
            Schema::hasColumn(
                'asset_categories',
                'coding_code'
            )
        ) {

            Schema::table(
                'asset_categories',
                function (Blueprint $table): void {

                    $table->dropColumn(
                        'coding_code'
                    );
                }
            );
        }
    }
};