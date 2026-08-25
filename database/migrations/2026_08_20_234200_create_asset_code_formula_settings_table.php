<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_code_formula_settings')) {
            return;
        }

        Schema::create('asset_code_formula_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->unique();
            $table->json('segment_order');
            $table->string('separator', 1)->default('-');
            $table->unsignedTinyInteger('site_length')->default(2);
            $table->unsignedTinyInteger('category_length')->default(2);
            $table->unsignedTinyInteger('type_length')->default(3);
            $table->unsignedTinyInteger('serial_length')->default(4);
            $table->string('sequence_scope', 30)->default('family');
            $table->boolean('enforce_segment_lengths')->default(false);
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_code_formula_settings');
    }
};
