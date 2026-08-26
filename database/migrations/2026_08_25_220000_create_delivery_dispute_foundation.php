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
            'delivery_disputes',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->foreignId('inventory_request_id')
                    ->constrained('inventory_requests')
                    ->cascadeOnDelete();

                $table->foreignId('workflow_instance_id')
                    ->nullable()
                    ->constrained('workflow_instances')
                    ->nullOnDelete();

                $table->foreignId('requester_receipt_step_id')
                    ->nullable()
                    ->constrained('workflow_instance_steps')
                    ->nullOnDelete();

                $table->foreignId('reported_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('reported_by_employee_id')
                    ->nullable()
                    ->constrained('employees')
                    ->nullOnDelete();

                $table->string('status', 50)
                    ->default('warehouse_pending');

                $table->string('reason_code', 60);
                $table->text('description')->nullable();

                $table->timestamp('reported_at');
                $table->timestamp('warehouse_received_at')->nullable();
                $table->timestamp('resolved_at')->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'company_id',
                        'status',
                        'reported_at',
                    ],
                    'delivery_disputes_company_status_idx'
                );

                $table->index(
                    [
                        'inventory_request_id',
                        'status',
                    ],
                    'delivery_disputes_request_status_idx'
                );
            }
        );

        Schema::create(
            'delivery_dispute_items',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->foreignId('delivery_dispute_id')
                    ->constrained('delivery_disputes')
                    ->cascadeOnDelete();

                $table->foreignId('inventory_request_allocation_id')
                    ->constrained('inventory_request_allocations')
                    ->cascadeOnDelete();

                $table->foreignId('inventory_request_item_id')
                    ->nullable()
                    ->constrained('inventory_request_items')
                    ->nullOnDelete();

                $table->foreignId('asset_id')
                    ->constrained('assets')
                    ->restrictOnDelete();

                $table->string('issue_type', 60)
                    ->default('other');

                $table->text('description')->nullable();

                $table->json('custody_snapshot')->nullable();

                $table->string('status', 50)
                    ->default('reported');

                $table->timestamps();

                $table->unique(
                    [
                        'delivery_dispute_id',
                        'inventory_request_allocation_id',
                    ],
                    'delivery_dispute_item_allocation_unique'
                );

                $table->index(
                    [
                        'company_id',
                        'asset_id',
                        'status',
                    ],
                    'delivery_dispute_items_asset_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_dispute_items');
        Schema::dropIfExists('delivery_disputes');
    }
};