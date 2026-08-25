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
        Schema::create(
            'asset_code_sequences',
            function (Blueprint $table): void {

                $table->id();

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'prefix',
                    150
                );

                $table->unsignedBigInteger(
                    'last_sequence'
                )
                    ->default(
                        0
                    );

                $table->timestamps();

                $table->unique(
                    [
                        'company_id',
                        'prefix',
                    ],
                    'asset_code_sequences_company_prefix_unique'
                );
            }
        );


        /*
         * Backfill from existing semantic codes.
         *
         * Examples:
         *
         * COMPUTER-LAPTOP-000001
         * COMPUTER-MOUSE-000004
         *
         * Legacy values such as:
         *
         * AST-0001
         * MOVEMENT-BROWSER-001
         *
         * are intentionally ignored unless they match
         * the current six-digit policy structure.
         */
        $assets =
            DB::table(
                'assets'
            )
                ->select([
                    'company_id',
                    'asset_code',
                ])
                ->whereNotNull(
                    'company_id'
                )
                ->whereNotNull(
                    'asset_code'
                )
                ->get();


        $maxima = [];


        foreach ($assets as $asset) {

            $code =
                trim(
                    (string) $asset->asset_code
                );


            if (
                preg_match(
                    '/^(.+)-(\d{6,})$/',
                    $code,
                    $matches
                ) !== 1
            ) {
                continue;
            }


            $companyId =
                (int) $asset->company_id;

            $prefix =
                trim(
                    (string) $matches[1]
                );

            $sequence =
                (int) $matches[2];


            if (
                $companyId <= 0
                || $prefix === ''
                || $sequence <= 0
            ) {
                continue;
            }


            $key =
                $companyId
                . '|'
                . $prefix;


            if (
                !isset(
                    $maxima[$key]
                )
                ||
                $sequence
                >
                $maxima[$key]['sequence']
            ) {

                $maxima[$key] = [
                    'company_id' =>
                        $companyId,

                    'prefix' =>
                        $prefix,

                    'sequence' =>
                        $sequence,
                ];
            }
        }


        foreach ($maxima as $item) {

            DB::table(
                'asset_code_sequences'
            )
                ->insert([
                    'company_id' =>
                        $item[
                            'company_id'
                        ],

                    'prefix' =>
                        $item[
                            'prefix'
                        ],

                    'last_sequence' =>
                        $item[
                            'sequence'
                        ],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
        }
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_code_sequences'
        );
    }
};