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
            'asset_category_approval_routes',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('asset_category_id')
                    ->constrained('asset_categories')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Process
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'process_type',
                    100
                )->default(
                    'inventory_request'
                );

                /*
                |--------------------------------------------------------------------------
                | Resolver
                |--------------------------------------------------------------------------
                |
                | employee
                | role
                | department_manager
                | direct_manager
                | ...
                |
                */

                $table->string(
                    'approver_type',
                    100
                );

                $table->unsignedBigInteger(
                    'approver_reference_id'
                )->nullable();

                $table->boolean(
                    'is_required'
                )->default(true);

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->unsignedInteger(
                    'sort_order'
                )->default(10);

                $table->json(
                    'settings'
                )->nullable();

                $table->timestamps();


                $table->unique(
                    [
                        'company_id',
                        'asset_category_id',
                        'process_type',
                    ],
                    'category_approval_route_unique'
                );

                $table->index([
                    'company_id',
                    'process_type',
                    'is_active',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_category_approval_routes'
        );
    }
};