<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_repair_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_repair_request_id')
                ->unique()
                ->constrained('asset_repair_requests')
                ->cascadeOnDelete();

            $table->string('work_order_number', 64);
            $table->string('repair_type', 20)->default('internal');

            $table->foreignId('assigned_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->string('external_provider_name', 255)->nullable();

            $table->string('status', 30)->default('planned');
            $table->string('outcome', 40)->nullable();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('expected_return_at')->nullable();
            $table->timestamp('actual_return_at')->nullable();

            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('parts_cost', 18, 2)->default(0);
            $table->decimal('external_service_cost', 18, 2)->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['company_id', 'work_order_number'],
                'repair_work_orders_company_number_unique'
            );

            $table->index(
                ['company_id', 'status'],
                'repair_work_orders_company_status_idx'
            );

            $table->index(
                ['company_id', 'repair_type', 'status'],
                'repair_work_orders_type_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_repair_work_orders');
    }
};