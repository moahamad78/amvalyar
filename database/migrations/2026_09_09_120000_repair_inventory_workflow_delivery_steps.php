<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $workflows = DB::table('workflows')->where('process_type', 'inventory_request')->pluck('id');
            foreach ($workflows as $workflowId) {
                if (DB::table('workflow_steps')->where('workflow_id', $workflowId)->where('code', 'FINAL-WAREHOUSE-DELIVERY')->exists()) {
                    continue;
                }
                $receipt = DB::table('workflow_steps')->where('workflow_id', $workflowId)->where('code', 'REQUESTER-RECEIPT')->first();
                $sort = $receipt ? (int) $receipt->sort_order : ((int) DB::table('workflow_steps')->where('workflow_id', $workflowId)->max('sort_order') + 10);
                if ($receipt) {
                    DB::table('workflow_steps')->where('id', $receipt->id)->update(['sort_order' => $sort + 10, 'updated_at' => now()]);
                }
                DB::table('workflow_steps')->insert([
                    'workflow_id' => $workflowId,
                    'name' => 'تحویل نهایی انبار و ثبت رسید',
                    'code' => 'FINAL-WAREHOUSE-DELIVERY',
                    'step_type' => 'approval',
                    'approver_type' => 'employee',
                    'approver_reference_id' => null,
                    'sort_order' => $sort,
                    'is_required' => true,
                    'is_active' => true,
                    'rejection_action' => 'return_previous',
                    'due_hours' => 24,
                    'conditions' => null,
                    'settings' => json_encode(['warehouse_delivery' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'description' => 'تحویل نهایی فیزیکی انبار پیش از تأیید دریافت متقاضی.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('workflow_steps')->where('code', 'FINAL-WAREHOUSE-DELIVERY')->delete();
    }
};
