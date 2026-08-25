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
            'asset_attribute_values',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('asset_id')
                    ->constrained('assets')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'asset_attribute_definition_id'
                )
                    ->constrained(
                        'asset_attribute_definitions'
                    )
                    ->cascadeOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Normalized Value Storage
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'value_text'
                )->nullable();

                $table->decimal(
                    'value_number',
                    20,
                    6
                )->nullable();

                $table->date(
                    'value_date'
                )->nullable();

                $table->boolean(
                    'value_boolean'
                )->nullable();

                $table->foreignId(
                    'option_id'
                )
                    ->nullable()
                    ->constrained(
                        'asset_attribute_options'
                    )
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Photo Attribute
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'file_path',
                    500
                )->nullable();

                $table->string(
                    'original_name',
                    255
                )->nullable();

                $table->string(
                    'mime_type',
                    100
                )->nullable();

                $table->unsignedBigInteger(
                    'file_size'
                )->nullable();


                $table->foreignId(
                    'updated_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();


                $table->unique(
                    [
                        'asset_id',
                        'asset_attribute_definition_id',
                    ],
                    'asset_attribute_value_unique'
                );

                $table->index([
                    'company_id',
                    'asset_id',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_attribute_values'
        );
    }
};