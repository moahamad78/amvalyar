<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable(
                'asset_movement_requests'
            )
        ) {
            return;
        }

        Schema::create(
            'asset_movement_requests',
            function (Blueprint $table): void {

                $table->id();

                $table->foreignId(
                    'company_id'
                )
                    ->constrained(
                        'companies'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->foreignId(
                    'asset_id'
                )
                    ->constrained(
                        'assets'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                /*
                 * transfer
                 * return
                 * disposal
                 */
                $table->string(
                    'movement_type',
                    30
                );

                /*
                 * فقط برای transfer اجباری است.
                 */
                $table->foreignId(
                    'target_user_id'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId(
                    'requested_by_user_id'
                )
                    ->constrained(
                        'users'
                    )
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->foreignId(
                    'requested_by_employee_id'
                )
                    ->nullable()
                    ->constrained(
                        'employees'
                    )
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->foreignId(
                    'workflow_instance_id'
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'workflow_instances'
                    )
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                /*
                 * draft
                 * submitted
                 * approved
                 * rejected
                 * completed
                 * cancelled
                 */
                $table->string(
                    'status',
                    30
                )
                    ->default(
                        'draft'
                    );

                $table->text(
                    'reason'
                )
                    ->nullable();

                $table->text(
                    'notes'
                )
                    ->nullable();

                $table->timestamp(
                    'submitted_at'
                )
                    ->nullable();

                $table->timestamp(
                    'completed_at'
                )
                    ->nullable();

                $table->timestamp(
                    'cancelled_at'
                )
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'company_id',
                    'movement_type',
                    'status',
                ]);

                $table->index([
                    'asset_id',
                    'status',
                ]);

                $table->index([
                    'requested_by_user_id',
                    'status',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'asset_movement_requests'
        );
    }
};