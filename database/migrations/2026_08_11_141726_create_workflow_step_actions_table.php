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
            'workflow_step_actions',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId(
                    'workflow_instance_id'
                )
                    ->constrained('workflow_instances')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'workflow_instance_step_id'
                )
                    ->nullable()
                    ->constrained('workflow_instance_steps')
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Action
                |--------------------------------------------------------------------------
                |
                | approve
                | reject
                | reactivate
                | complete
                | cancel
                |
                */

                $table->string(
                    'action',
                    50
                );

                $table->string(
                    'from_status',
                    50
                )
                    ->nullable();

                $table->string(
                    'to_status',
                    50
                )
                    ->nullable();

                $table->foreignId(
                    'actor_employee_id'
                )
                    ->nullable()
                    ->constrained('employees')
                    ->nullOnDelete();

                $table->foreignId(
                    'actor_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text(
                    'comment'
                )
                    ->nullable();

                $table->json(
                    'metadata'
                )
                    ->nullable();

                $table->timestamp(
                    'acted_at'
                );

                $table->timestamps();

                $table->index([
                    'workflow_instance_id',
                    'acted_at',
                ]);

                $table->index([
                    'workflow_instance_step_id',
                    'action',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'workflow_step_actions'
        );
    }
};