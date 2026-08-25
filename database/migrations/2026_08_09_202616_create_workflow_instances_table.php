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
            'workflow_instances',
            function (Blueprint $table): void {

                $table->id();


                $table->foreignId('company_id')
                    ->constrained()
                    ->cascadeOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Original Workflow
                |--------------------------------------------------------------------------
                */

                $table->foreignId('workflow_id')
                    ->nullable()
                    ->constrained('workflows')
                    ->nullOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Workflow Snapshot
                |--------------------------------------------------------------------------
                |
                | درخواست باید حتی اگر Workflow اصلی بعداً تغییر کرد
                | همچنان با نسخه زمان شروع خودش ادامه پیدا کند.
                |
                */

                $table->string(
                    'workflow_name',
                    255
                );

                $table->string(
                    'workflow_code',
                    100
                );

                $table->string(
                    'process_type',
                    100
                );

                $table->unsignedInteger(
                    'workflow_version'
                )
                    ->default(1);


                /*
                |--------------------------------------------------------------------------
                | Subject
                |--------------------------------------------------------------------------
                |
                | موتور Workflow مستقل از نوع درخواست است.
                |
                | مثال:
                |
                | subject_type = App\Models\InventoryRequest
                | subject_id   = 15
                |
                | یا:
                |
                | subject_type = App\Models\AssetTransferRequest
                |
                */

                $table->nullableMorphs(
                    'subject'
                );


                /*
                |--------------------------------------------------------------------------
                | Requester
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'requester_employee_id'
                )
                    ->nullable()
                    ->constrained('employees')
                    ->nullOnDelete();


                $table->foreignId(
                    'requester_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Runtime Status
                |--------------------------------------------------------------------------
                |
                | draft
                | pending
                | completed
                | rejected
                | cancelled
                |
                */

                $table->string(
                    'status',
                    50
                )
                    ->default('pending');


                $table->foreignId(
                    'current_step_id'
                )
                    ->nullable();


                $table->timestamp(
                    'started_at'
                )
                    ->nullable();


                $table->timestamp(
                    'completed_at'
                )
                    ->nullable();


                $table->timestamp(
                    'rejected_at'
                )
                    ->nullable();


                $table->timestamp(
                    'cancelled_at'
                )
                    ->nullable();


                /*
                |--------------------------------------------------------------------------
                | Runtime Context
                |--------------------------------------------------------------------------
                |
                | اطلاعاتی که Rule Engine آینده برای تصمیم‌گیری لازم دارد.
                |
                */

                $table->json(
                    'context'
                )
                    ->nullable();


                $table->text(
                    'description'
                )
                    ->nullable();


                $table->timestamps();


                $table->index([
                    'company_id',
                    'status',
                ]);


                $table->index([
                    'company_id',
                    'process_type',
                    'status',
                ]);


                $table->index(
                    'current_step_id'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'workflow_instances'
        );
    }
};