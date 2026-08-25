<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_plate_templates')) {
            return;
        }

        Schema::create(
            'asset_plate_templates',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 120);
                $table->decimal('width_mm', 8, 2)->default(50);
                $table->decimal('height_mm', 8, 2)->default(30);
                $table->string('orientation', 20)->default('landscape');
                $table->json('elements');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->index(
                    ['company_id', 'is_active'],
                    'asset_plate_templates_company_active_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_plate_templates');
    }
};
