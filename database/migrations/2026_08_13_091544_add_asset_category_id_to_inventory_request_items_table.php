<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'inventory_request_items',
            function (Blueprint $table): void {

                $table->foreignId(
                    'asset_category_id'
                )
                    ->nullable()
                    ->after(
                        'inventory_request_id'
                    )
                    ->constrained(
                        'asset_categories'
                    )
                    ->nullOnDelete();

                $table->index([
                    'inventory_request_id',
                    'asset_category_id',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'inventory_request_items',
            function (Blueprint $table): void {

                $table->dropForeign([
                    'asset_category_id',
                ]);

                $table->dropIndex([
                    'inventory_request_id',
                    'asset_category_id',
                ]);

                $table->dropColumn(
                    'asset_category_id'
                );
            }
        );
    }
};