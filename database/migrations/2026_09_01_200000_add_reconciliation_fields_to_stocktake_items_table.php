<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocktake_items', function (Blueprint $table): void {
            $table->string('reconciliation_status', 30)->default('pending')->after('notes');
            $table->string('reconciliation_action', 50)->nullable()->after('reconciliation_status');
            $table->foreignId('reconciled_by')->nullable()->after('reconciliation_action')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable()->after('reconciled_by');
            $table->text('reconciliation_note')->nullable()->after('reconciled_at');

            $table->index(['company_id', 'reconciliation_status'], 'stocktake_items_reconciliation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stocktake_items', function (Blueprint $table): void {
            $table->dropIndex('stocktake_items_reconciliation_idx');
            $table->dropForeign(['reconciled_by']);
            $table->dropColumn([
                'reconciliation_status',
                'reconciliation_action',
                'reconciled_by',
                'reconciled_at',
                'reconciliation_note',
            ]);
        });
    }
};