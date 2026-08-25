<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table): void {

            $table->id();

            $table->foreignId('workflow_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 255);

            $table->string('code', 100);

            /*
            |--------------------------------------------------------------------------
            | Step Type
            |--------------------------------------------------------------------------
            |
            | approval  = مرحله تأیید
            | action    = انجام عملیات
            | notification = اعلان
            | condition = شرط
            |
            */

            $table->string('step_type', 50)
                ->default('approval');

            /*
            |--------------------------------------------------------------------------
            | Approver Type
            |--------------------------------------------------------------------------
            |
            | این ستون قلب انعطاف‌پذیری Workflow است.
            |
            | direct_manager
            | employee
            | role
            | department_manager
            | asset_manager
            | warehouse_manager
            | requester
            | system
            |
            */

            $table->string('approver_type', 100)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Approver Reference
            |--------------------------------------------------------------------------
            |
            | بسته به approver_type ممکن است ID کارمند، Role،
            | Department یا چیز دیگری باشد.
            |
            */

            $table->unsignedBigInteger('approver_reference_id')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Step Order
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('sort_order')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Required / Skippable
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_required')
                ->default(true);

            $table->boolean('is_active')
                ->default(true);

            /*
            |--------------------------------------------------------------------------
            | Rejection Behavior
            |--------------------------------------------------------------------------
            |
            | terminate = پایان درخواست
            | return_previous = برگشت به مرحله قبلی
            | return_requester = برگشت به درخواست‌کننده
            |
            */

            $table->string('rejection_action', 50)
                ->default('terminate');

            /*
            |--------------------------------------------------------------------------
            | SLA
            |--------------------------------------------------------------------------
            |
            | زمان مجاز برای انجام مرحله.
            | null یعنی محدودیت زمانی ندارد.
            |
            */

            $table->unsignedInteger('due_hours')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Future Dynamic Rules
            |--------------------------------------------------------------------------
            |
            | JSON برای شرط‌های مرحله:
            |
            | مبلغ > X
            | نوع دارایی = خودرو
            | واحد درخواست‌کننده = تولید
            | سایت = شکوهیه
            | ...
            |
            */

            $table->json('conditions')
                ->nullable();

            $table->json('settings')
                ->nullable();

            $table->text('description')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'workflow_id',
                'code',
            ]);

            $table->index([
                'workflow_id',
                'sort_order',
                'is_active',
            ]);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};