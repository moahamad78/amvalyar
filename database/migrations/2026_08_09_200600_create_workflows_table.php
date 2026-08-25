<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table): void {

            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 255);

            $table->string('code', 100);

            /*
            |--------------------------------------------------------------------------
            | Process Type
            |--------------------------------------------------------------------------
            |
            | نوع فرآیندی که این Workflow برای آن استفاده می‌شود.
            |
            | فعلاً رشته‌ای نگه می‌داریم تا بعداً بدون Migration
            | انواع جدید فرایند اضافه کنیم.
            |
            | مثال:
            |
            | asset_request
            | asset_delivery
            | asset_transfer
            | asset_return
            | asset_disposal
            | asset_repair
            |
            */

            $table->string('process_type', 100);

            $table->text('description')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            /*
            |--------------------------------------------------------------------------
            | Default Workflow
            |--------------------------------------------------------------------------
            |
            | هر شرکت می‌تواند چند Workflow برای یک نوع فرایند داشته باشد،
            | ولی بعداً یکی از آنها می‌تواند پیش‌فرض انتخاب شود.
            |
            */

            $table->boolean('is_default')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Version
            |--------------------------------------------------------------------------
            |
            | برای آینده:
            | اگر Workflow استفاده‌شده را ویرایش کنیم،
            | نسخه قبلی را نگه می‌داریم تا درخواست‌های قدیمی خراب نشوند.
            |
            */

            $table->unsignedInteger('version')
                ->default(1);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'company_id',
                'code',
            ]);

            $table->index([
                'company_id',
                'process_type',
                'is_active',
            ]);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};