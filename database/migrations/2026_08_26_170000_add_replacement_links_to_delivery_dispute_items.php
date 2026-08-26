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
            'delivery_dispute_items',
            function (Blueprint $table): void {
                $table->foreignId('replacement_asset_id')
                    ->nullable()
                    ->after('asset_id')
                    ->constrained('assets')
                    ->nullOnDelete();

                $table->foreignId('replacement_allocation_id')
                    ->nullable()
                    ->after('replacement_asset_id')
                    ->constrained('inventory_request_allocations')
                    ->nullOnDelete();

                $table->timestamp('replacement_selected_at')
                    ->nullable()
                    ->after('status');

                $table->index(
                    [
                        'delivery_dispute_id',
                        'status',
                        'replacement_asset_id',
                    ],
                    'delivery_dispute_replacement_lookup'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'delivery_dispute_items',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'delivery_dispute_replacement_lookup'
                );

                $table->dropForeign([
                    'replacement_allocation_id',
                ]);

                $table->dropForeign([
                    'replacement_asset_id',
                ]);

                $table->dropColumn([
                    'replacement_allocation_id',
                    'replacement_asset_id',
                    'replacement_selected_at',
                ]);
            }
        );
    }
};