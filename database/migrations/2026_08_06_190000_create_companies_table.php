<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code')->unique();

            $table->string('manager_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('national_id')->nullable();
            $table->string('economic_code')->nullable();

            $table->text('address')->nullable();

            $table->date('license_start')->nullable();
            $table->date('license_end')->nullable();

            $table->unsignedInteger('max_users')->default(10);
            $table->unsignedInteger('max_assets')->default(1000);

            $table->string('plan')->default('basic');

            $table->enum('status', [
                'active',
                'suspended',
                'expired',
                'demo'
            ])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
