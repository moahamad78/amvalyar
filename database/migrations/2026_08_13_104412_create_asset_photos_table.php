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
            'asset_photos',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('asset_id')
                    ->constrained('assets')
                    ->cascadeOnDelete();

                $table->string(
                    'path',
                    500
                );

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

                $table->string(
                    'photo_type',
                    50
                )->default('general');

                $table->string(
                    'caption',
                    255
                )->nullable();

                $table->boolean(
                    'is_primary'
                )->default(false);

                $table->unsignedInteger(
                    'sort_order'
                )->default(10);

                $table->foreignId(
                    'uploaded_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();


                $table->index([
                    'company_id',
                    'asset_id',
                ]);

                $table->index([
                    'asset_id',
                    'is_primary',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_photos'
        );
    }
};