<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $workflows = DB::table('workflows')
            ->where('process_type', 'inventory_request')
            ->get(['id']);

        foreach ($workflows as $workflow) {
            $exists = DB::table('workflow_steps')
                ->where('workflow_id', $workflow->id)
                ->where('code', 'REQUESTER-RECEIPT')
                ->exists();

            if ($exists) {
                continue;
            }

            $maxSort = (int) DB::table('workflow_steps')
                ->where('workflow_id', $workflow->id)
                ->max('sort_order');

            DB::table('workflow_steps')->insert([
                'workflow_id' => $workflow->id,
                'name' => 'تأیید دریافت توسط درخواست‌کننده',
                'code' => 'REQUESTER-RECEIPT',
                'step_type' => 'approval',
                'approver_type' => 'requester',
                'approver_reference_id' => null,
                'sort_order' => max(50, $maxSort + 10),
                'is_required' => true,
                'is_active' => true,
                'rejection_action' => 'return_previous',
                'due_hours' => null,
                'conditions' => null,
                'settings' => json_encode([
                    'receipt_confirmation' => true,
                    'delivery_dispute_requires_dedicated_flow' => true,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'description' => 'تأیید نهایی دریافت یا استقرار دارایی پس از تحویل فیزیکی انبار.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('workflow_steps')
            ->where('code', 'REQUESTER-RECEIPT')
            ->whereIn(
                'workflow_id',
                DB::table('workflows')
                    ->where('process_type', 'inventory_request')
                    ->pluck('id')
            )
            ->delete();
    }
};