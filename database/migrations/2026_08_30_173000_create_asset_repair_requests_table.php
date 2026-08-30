<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_repair_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->string('status', 50)->default('draft');
            $table->string('priority', 30)->default('normal');
            $table->string('title', 255);
            $table->text('problem_description');
            $table->text('diagnosis')->nullable();
            $table->text('repair_notes')->nullable();
            $table->decimal('estimated_cost', 18, 2)->nullable();
            $table->decimal('actual_cost', 18, 2)->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'asset_id', 'status']);
            $table->index(['company_id', 'priority', 'status']);
            $table->index('workflow_instance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_repair_requests');
    }
};