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
            'inventory_requests',
            function (Blueprint $table): void {

                $table->id();


                $table->foreignId(
                    'company_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Request Number
                |--------------------------------------------------------------------------
                |
                | شماره نمایشی درخواست.
                | در هر شرکت باید یکتا باشد.
                |
                */

                $table->string(
                    'request_number',
                    100
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
                | Organizational Context
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'site_id'
                )
                    ->nullable()
                    ->constrained('sites')
                    ->nullOnDelete();


                $table->foreignId(
                    'department_id'
                )
                    ->nullable()
                    ->constrained('departments')
                    ->nullOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Runtime Workflow
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'workflow_instance_id'
                )
                    ->nullable()
                    ->constrained('workflow_instances')
                    ->nullOnDelete();


                /*
                |--------------------------------------------------------------------------
                | Status
                |--------------------------------------------------------------------------
                |
                | draft
                | submitted
                | in_approval
                | approved
                | rejected
                | cancelled
                | fulfilled
                | partially_fulfilled
                |
                */

                $table->string(
                    'status',
                    50
                )
                    ->default('draft');


                $table->string(
                    'priority',
                    30
                )
                    ->default('normal');


                $table->text(
                    'purpose'
                )
                    ->nullable();


                $table->text(
                    'description'
                )
                    ->nullable();


                $table->timestamp(
                    'submitted_at'
                )
                    ->nullable();


                $table->timestamp(
                    'approved_at'
                )
                    ->nullable();


                $table->timestamp(
                    'rejected_at'
                )
                    ->nullable();


                $table->timestamp(
                    'fulfilled_at'
                )
                    ->nullable();


                $table->timestamps();


                $table->unique([
                    'company_id',
                    'request_number',
                ]);


                $table->index([
                    'company_id',
                    'status',
                ]);


                $table->index([
                    'company_id',
                    'requester_employee_id',
                    'status',
                ]);


                $table->index([
                    'company_id',
                    'site_id',
                    'department_id',
                ]);
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_requests'
        );
    }
};