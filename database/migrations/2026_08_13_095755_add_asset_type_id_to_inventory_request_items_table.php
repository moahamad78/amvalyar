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
                    'asset_type_id'
                )
                    ->nullable()
                    ->after(
                        'asset_category_id'
                    )
                    ->constrained(
                        'asset_types'
                    )
                    ->nullOnDelete();

                $table->index([
                    'inventory_request_id',
                    'asset_type_id',
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
                    'asset_type_id',
                ]);

                $table->dropIndex([
                    'inventory_request_id',
                    'asset_type_id',
                ]);

                $table->dropColumn(
                    'asset_type_id'
                );
            }
        );
    }
};