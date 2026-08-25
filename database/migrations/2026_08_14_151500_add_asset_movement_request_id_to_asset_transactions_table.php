<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasColumn(
                'asset_transactions',
                'asset_movement_request_id'
            )
        ) {
            return;
        }

        Schema::table(
            'asset_transactions',
            function (Blueprint $table): void {

                $table->foreignId(
                    'asset_movement_request_id'
                )
                    ->nullable()
                    ->after(
                        'asset_id'
                    )
                    ->unique()
                    ->constrained(
                        'asset_movement_requests'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
        );
    }


    public function down(): void
    {
        if (
            !Schema::hasColumn(
                'asset_transactions',
                'asset_movement_request_id'
            )
        ) {
            return;
        }

        Schema::table(
            'asset_transactions',
            function (Blueprint $table): void {

                $table->dropForeign([
                    'asset_movement_request_id',
                ]);

                $table->dropUnique([
                    'asset_movement_request_id',
                ]);

                $table->dropColumn(
                    'asset_movement_request_id'
                );
            }
        );
    }
};