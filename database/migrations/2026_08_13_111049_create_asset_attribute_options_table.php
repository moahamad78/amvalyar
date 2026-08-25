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
            'asset_attribute_options',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId(
                    'asset_attribute_definition_id'
                )
                    ->constrained(
                        'asset_attribute_definitions'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'label',
                    255
                );

                $table->string(
                    'value',
                    255
                );

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->unsignedInteger(
                    'sort_order'
                )->default(10);

                $table->timestamps();


                $table->unique(
                    [
                        'asset_attribute_definition_id',
                        'value',
                    ],
                    'asset_attribute_option_unique'
                );

                $table->index([
                    'asset_attribute_definition_id',
                    'is_active',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_attribute_options'
        );
    }
};