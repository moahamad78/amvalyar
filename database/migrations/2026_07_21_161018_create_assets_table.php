<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {

            $table->id();

            $table->foreignId('asset_category_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('inventory_code',100)
                ->unique()
                ->nullable();

            $table->string('asset_code',100)
                ->unique();

            $table->string('title',255);

            $table->string('brand',150)
                ->nullable();

            $table->string('model',150)
                ->nullable();

            $table->string('serial_number',150)
                ->nullable();

            $table->string('manufacturer',150)
                ->nullable();

            $table->string('country',100)
                ->nullable();

            $table->date('purchase_date')
                ->nullable();

            $table->decimal('purchase_price',18,2)
                ->default(0);

            $table->text('description')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};