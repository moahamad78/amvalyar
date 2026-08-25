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
            'workflow_instance_branches',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId(
                    'company_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId(
                    'workflow_instance_id'
                )
                    ->constrained('workflow_instances')
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Parent Runtime Step
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'parent_step_id'
                )
                    ->nullable()
                    ->constrained('workflow_instance_steps')
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Category
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'asset_category_id'
                )
                    ->nullable()
                    ->constrained('asset_categories')
                    ->nullOnDelete();


                $table->string(
                    'branch_key',
                    150
                );

                $table->string(
                    'name',
                    255
                );

                /*
                |--------------------------------------------------------------------------
                | Approver Snapshot
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'approver_type',
                    100
                );

                $table->unsignedBigInteger(
                    'approver_reference_id'
                )->nullable();

                $table->foreignId(
                    'resolved_employee_id'
                )
                    ->nullable()
                    ->constrained('employees')
                    ->nullOnDelete();

                $table->foreignId(
                    'resolved_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Runtime
                |--------------------------------------------------------------------------
                |
                | waiting
                | pending
                | approved
                | rejected
                | skipped
                | cancelled
                |
                */

                $table->string(
                    'status',
                    50
                )->default('waiting');

                $table->boolean(
                    'is_required'
                )->default(true);

                $table->unsignedInteger(
                    'sort_order'
                )->default(10);

                $table->timestamp(
                    'activated_at'
                )->nullable();

                $table->timestamp(
                    'acted_at'
                )->nullable();

                $table->foreignId(
                    'acted_by_employee_id'
                )
                    ->nullable()
                    ->constrained('employees')
                    ->nullOnDelete();

                $table->foreignId(
                    'acted_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text(
                    'comment'
                )->nullable();

                $table->json(
                    'settings'
                )->nullable();

                $table->timestamps();


                $table->unique(
                    [
                        'workflow_instance_id',
                        'parent_step_id',
                        'branch_key',
                    ],
                    'workflow_branch_unique'
                );

                $table->index([
                    'company_id',
                    'status',
                ]);

                $table->index([
                    'resolved_user_id',
                    'status',
                ]);

                $table->index([
                    'resolved_employee_id',
                    'status',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'workflow_instance_branches'
        );
    }
};