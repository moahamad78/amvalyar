<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_repair_requests', function (Blueprint $table): void {
            $table->string('cancelled_from_status', 50)->nullable()->after('cancelled_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_from_status');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('cancelled_by_user_id');
            $table->text('reopen_reason')->nullable()->after('reopened_at');
            $table->foreignId('reopened_by_user_id')->nullable()->after('reopen_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_repair_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reopened_by_user_id');
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['cancelled_from_status', 'cancellation_reason', 'reopened_at', 'reopen_reason']);
        });
    }
};
