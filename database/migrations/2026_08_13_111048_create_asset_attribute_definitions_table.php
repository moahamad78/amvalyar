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
            'asset_attribute_definitions',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('asset_type_id')
                    ->constrained('asset_types')
                    ->cascadeOnDelete();

                $table->string('name', 255);

                $table->string('code', 100);

                /*
                |--------------------------------------------------------------------------
                | Data Type
                |--------------------------------------------------------------------------
                |
                | text
                | textarea
                | number
                | date
                | boolean
                | select
                | photo
                |
                */

                $table->string(
                    'data_type',
                    50
                );

                /*
                |--------------------------------------------------------------------------
                | Requirement Stage
                |--------------------------------------------------------------------------
                |
                | optional
                | warehouse_entry
                | asset_manager_review
                | before_delivery
                |
                */

                $table->string(
                    'required_stage',
                    50
                )->default('optional');

                $table->string(
                    'unit',
                    100
                )->nullable();

                $table->string(
                    'placeholder',
                    255
                )->nullable();

                $table->text(
                    'help_text'
                )->nullable();

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
                        'asset_type_id',
                        'code',
                    ],
                    'asset_attribute_definition_unique'
                );

                $table->index([
                    'company_id',
                    'asset_type_id',
                    'is_active',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_attribute_definitions'
        );
    }
};