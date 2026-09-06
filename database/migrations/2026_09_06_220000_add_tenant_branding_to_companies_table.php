<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('brand_logo_path')->nullable();
            $table->string('brand_primary_color', 7)->nullable();
            $table->string('brand_secondary_color', 7)->nullable();
            $table->string('brand_accent_color', 7)->nullable();
            $table->string('brand_surface_color', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'brand_logo_path',
                'brand_primary_color',
                'brand_secondary_color',
                'brand_accent_color',
                'brand_surface_color',
            ]);
        });
    }
};
