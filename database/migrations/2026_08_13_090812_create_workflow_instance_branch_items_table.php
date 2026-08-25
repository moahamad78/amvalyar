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
            'workflow_instance_branch_items',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId(
                    'workflow_instance_branch_id'
                )
                    ->constrained('workflow_instance_branches')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Request Item
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'inventory_request_item_id'
                )
                    ->nullable()
                    ->constrained('inventory_request_items')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Concrete Asset
                |--------------------------------------------------------------------------
                |
                | بعد از Allocation انباردار پر می‌شود.
                |
                */

                $table->foreignId(
                    'asset_id'
                )
                    ->nullable()
                    ->constrained('assets')
                    ->nullOnDelete();

                $table->timestamps();


                $table->unique(
                    [
                        'workflow_instance_branch_id',
                        'inventory_request_item_id',
                        'asset_id',
                    ],
                    'workflow_branch_item_unique'
                );

                $table->index(
                    'inventory_request_item_id'
                );

                $table->index(
                    'asset_id'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'workflow_instance_branch_items'
        );
    }
};