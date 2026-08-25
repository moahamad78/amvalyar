<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            /*
             * حذف Unique سراسری
             */
            $table->dropUnique(
                'assets_asset_code_unique'
            );

            $table->dropUnique(
                'assets_inventory_code_unique'
            );


            /*
             * Unique داخل هر شرکت
             */
            $table->unique(
                [
                    'company_id',
                    'asset_code',
                ],
                'assets_company_asset_code_unique'
            );

            $table->unique(
                [
                    'company_id',
                    'inventory_code',
                ],
                'assets_company_inventory_code_unique'
            );
        });
    }


    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            $table->dropUnique(
                'assets_company_asset_code_unique'
            );

            $table->dropUnique(
                'assets_company_inventory_code_unique'
            );


            $table->unique(
                'asset_code',
                'assets_asset_code_unique'
            );

            $table->unique(
                'inventory_code',
                'assets_inventory_code_unique'
            );
        });
    }
};