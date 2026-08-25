<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'inventory_request_allocations',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('inventory_request_id')
                    ->constrained('inventory_requests')
                    ->cascadeOnDelete();

                $table->foreignId('inventory_request_item_id')
                    ->constrained('inventory_request_items')
                    ->cascadeOnDelete();

                $table->foreignId('asset_id')
                    ->constrained('assets')
                    ->restrictOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Allocation lifecycle
                |--------------------------------------------------------------------------
                |
                | reserved
                | approved
                | released
                | delivered
                | receipt_confirmed
                | receipt_disputed
                |
                */

                $table->string(
                    'status',
                    50
                )->default('reserved');

                $table->foreignId(
                    'reserved_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'reserved_by_employee_id'
                )
                    ->nullable()
                    ->constrained('employees')
                    ->nullOnDelete();

                $table->timestamp(
                    'reserved_at'
                )->nullable();

                $table->timestamp(
                    'approved_at'
                )->nullable();

                $table->timestamp(
                    'released_at'
                )->nullable();

                $table->timestamp(
                    'delivered_at'
                )->nullable();

                $table->timestamp(
                    'receipt_confirmed_at'
                )->nullable();

                $table->text(
                    'note'
                )->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | One Asset can appear only once in the same request
                |--------------------------------------------------------------------------
                */

                $table->unique(
                    [
                        'inventory_request_id',
                        'asset_id',
                    ],
                    'inventory_request_asset_unique'
                );

                $table->index([
                    'company_id',
                    'status',
                ]);

                $table->index([
                    'inventory_request_item_id',
                    'status',
                ]);

                $table->index([
                    'asset_id',
                    'status',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_request_allocations'
        );
    }
};