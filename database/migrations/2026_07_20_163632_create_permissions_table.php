<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            $table->string(
                'name',
                150
            )->unique();

            $table->string(
                'display_name',
                255
            );

            $table->text(
                'description'
            )->nullable();

            $table->boolean(
                'is_active'
            )->default(true);

            $table->timestamps();

            $table->index(
                'is_active'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};