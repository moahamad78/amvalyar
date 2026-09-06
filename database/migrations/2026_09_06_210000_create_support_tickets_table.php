<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('tracking_code', 24)->unique();
            $table->string('type', 24)->index();
            $table->string('name', 100);
            $table->string('mobile', 16);
            $table->string('email')->nullable();
            $table->string('organization', 150)->nullable();
            $table->text('message');
            $table->string('status', 24)->default('new')->index();
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
