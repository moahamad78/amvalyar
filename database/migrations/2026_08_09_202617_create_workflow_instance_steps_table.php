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
            'workflow_instance_steps',
            function (Blueprint $table): void {

                $table->id();


                $table->foreignId(
                    'workflow_instance_id'
                )
                    ->constrained(
                        'workflow_instances'
                    )
                    ->cascadeOnDelete();


                $table->foreignId(
                    'workflow_step_id'
                )
                    ->nullable()
                    ->constrained(
                        'workflow_steps'
                    )
                    ->nullOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Snapshot
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'name',
                    255
                );

                $table->string(
                    'code',
                    100
                );

                $table->string(
                    'step_type',
                    50
                );

                $table->string(
                    'approver_type',
                    100
                )
                    ->nullable();

                $table->unsignedBigInteger(
                    'approver_reference_id'
                )
                    ->nullable();

                $table->unsignedInteger(
                    'sort_order'
                )
                    ->default(0);

                $table->boolean(
                    'is_required'
                )
                    ->default(true);

                $table->string(
                    'rejection_action',
                    50
                )
                    ->default('terminate');

                $table->unsignedInteger(
                    'due_hours'
                )
                    ->nullable();

                $table->json(
                    'conditions'
                )
                    ->nullable();

                $table->json(
                    'settings'
                )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Resolved Approver
                |--------------------------------------------------------------------------
                |
                | approver_type می‌گوید قانون چیست.
                | resolved_approver_* می‌گوید در این درخواست خاص
                | چه کسی واقعاً مسئول مرحله شده.
                |
                */

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
                | Status
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
                )
                    ->default('waiting');


                $table->timestamp(
                    'activated_at'
                )
                    ->nullable();

                $table->timestamp(
                    'due_at'
                )
                    ->nullable();

                $table->timestamp(
                    'acted_at'
                )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Actor
                |--------------------------------------------------------------------------
                */

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
                )
                    ->nullable();


                $table->timestamps();


                $table->index([
                    'workflow_instance_id',
                    'sort_order',
                ]);


                $table->index([
                    'status',
                    'resolved_user_id',
                ]);


                $table->index([
                    'status',
                    'resolved_employee_id',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'workflow_instance_steps'
        );
    }
};