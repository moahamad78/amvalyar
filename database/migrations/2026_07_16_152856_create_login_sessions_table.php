<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_sessions', function (Blueprint $table): void {
            $table->id();

            $table->string('session_id', 64)
                ->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->ipAddress('ip_address');

            $table->text('user_agent');

            $table->string('computer_name', 255);

            $table->timestamp('started_at');

            $table->timestamp('last_activity_at')
                ->nullable();

            $table->timestamp('revoked_at')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['user_id', 'revoked_at'],
                'login_sessions_user_active_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_sessions');
    }
};