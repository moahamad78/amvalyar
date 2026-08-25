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
         * The original schema allowed only one route for each
         * company/category/process, while the runtime already supports
         * one OR MORE parallel specialist routes.
         *
         * Remove that legacy uniqueness rule and keep a normal lookup index.
         */
        Schema::table(
            'asset_category_approval_routes',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'category_approval_route_unique'
                );

                $table->index(
                    [
                        'company_id',
                        'asset_category_id',
                        'process_type',
                    ],
                    'category_approval_route_lookup'
                );
            }
        );
    }

    public function down(): void
    {
        $duplicates = DB::table(
            'asset_category_approval_routes'
        )
            ->selectRaw(
                'company_id, asset_category_id, process_type, COUNT(*) AS aggregate'
            )
            ->groupBy(
                'company_id',
                'asset_category_id',
                'process_type'
            )
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new \RuntimeException(
                'Cannot restore the legacy one-route-per-category constraint while multiple specialist routes exist.'
            );
        }

        Schema::table(
            'asset_category_approval_routes',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'category_approval_route_lookup'
                );

                $table->unique(
                    [
                        'company_id',
                        'asset_category_id',
                        'process_type',
                    ],
                    'category_approval_route_unique'
                );
            }
        );
    }
};