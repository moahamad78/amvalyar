<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktake_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('stocktake_id')
                ->constrained('stocktakes')
                ->cascadeOnDelete();

            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();

            $table->string('expected_status', 50)
                ->nullable();

            $table->string('expected_custody_type', 50)
                ->nullable();

            $table->foreignId('expected_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('expected_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('expected_department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->foreignId('expected_site_id')
                ->nullable()
                ->constrained('sites')
                ->nullOnDelete();

            $table->foreignId('expected_location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();

            $table->string('result_status', 50)
                ->default('pending');

            $table->string('observed_custody_type', 50)
                ->nullable();

            $table->foreignId('observed_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('observed_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('observed_department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->foreignId('observed_site_id')
                ->nullable()
                ->constrained('sites')
                ->nullOnDelete();

            $table->foreignId('observed_location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();

            $table->foreignId('counted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('counted_at')
                ->nullable();

            $table->unsignedInteger('count_round')
                ->default(0);

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'stocktake_id',
                'asset_id',
            ]);

            $table->index([
                'company_id',
                'result_status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_items');
    }
};