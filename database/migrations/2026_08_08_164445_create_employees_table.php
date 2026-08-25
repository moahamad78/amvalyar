<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {

            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('site_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('manager_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->string('personnel_code', 100);

            $table->string('first_name')
                ->nullable();

            $table->string('last_name')
                ->nullable();

            $table->string('display_name');

            $table->string('job_title')
                ->nullable();

            $table->string('national_code', 20)
                ->nullable();

            $table->string('email')
                ->nullable();

            $table->string('phone', 30)
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->text('description')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'company_id',
                'personnel_code',
            ]);

            $table->unique(
                'user_id'
            );

            $table->index([
                'company_id',
                'department_id',
            ]);

            $table->index([
                'company_id',
                'site_id',
            ]);

            $table->index([
                'company_id',
                'manager_employee_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};