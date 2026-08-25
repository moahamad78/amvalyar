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
            'asset_types',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('asset_category_id')
                    ->constrained('asset_categories')
                    ->restrictOnDelete();

                $table->string(
                    'name',
                    255
                );

                $table->string(
                    'code',
                    100
                );

                $table->text(
                    'description'
                )->nullable();

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->unsignedInteger(
                    'sort_order'
                )->default(10);

                $table->timestamps();


                $table->unique(
                    [
                        'company_id',
                        'code',
                    ],
                    'asset_types_company_code_unique'
                );

                $table->index([
                    'company_id',
                    'asset_category_id',
                    'is_active',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_types'
        );
    }
};