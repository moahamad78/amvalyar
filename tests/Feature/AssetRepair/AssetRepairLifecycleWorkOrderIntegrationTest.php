<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\AssetRepairWorkOrder;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\AssetRepairLifecycleService;
use App\Services\AssetRepairRequestService;
use App\Services\AssetRepairWorkOrderService;
use App\Services\WorkflowRuntimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssetRepairLifecycleWorkOrderIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_start_creates_and_starts_work_order_atomically(): void
    {
        [$repair, $user] = $this->approvedRepair('START');
        $repair = app(AssetRepairLifecycleService::class)->startRepair($repair, $user);

        $this->assertSame(AssetRepairRequest::STATUS_IN_REPAIR, $repair->status);
        $this->assertSame(AssetRepairWorkOrder::STATUS_IN_PROGRESS, $repair->workOrder->status);
        $this->assertNotNull($repair->workOrder->received_at);
        $this->assertSame($repair->company_id, $repair->workOrder->company_id);
    }

    public function test_completion_synchronizes_outcome_and_cost(): void
    {
        [$repair, $user] = $this->approvedRepair('DONE');
        $repair = app(AssetRepairLifecycleService::class)->startRepair($repair, $user);
        $repair->workOrder->update(['parts_cost' => 250]);

        $repair = app(AssetRepairLifecycleService::class)->completeRepair(
            $repair, $user, 'Power fault', 'Repaired and tested', 1000,
            AssetRepairWorkOrder::OUTCOME_PARTIALLY_REPAIRED
        );

        $this->assertSame(AssetRepairRequest::STATUS_COMPLETED, $repair->status);
        $this->assertSame('1000.00', $repair->actual_cost);
        $this->assertSame(AssetRepairWorkOrder::STATUS_COMPLETED, $repair->workOrder->status);
        $this->assertSame(AssetRepairWorkOrder::OUTCOME_PARTIALLY_REPAIRED, $repair->workOrder->outcome);
        $this->assertSame('1000.00', $repair->workOrder->total_cost);
        $this->assertNotNull($repair->workOrder->actual_return_at);
    }

    public function test_completion_without_work_order_is_rejected(): void
    {
        [$repair, $user] = $this->approvedRepair('NO-WO');
        $repair->update(['status' => AssetRepairRequest::STATUS_IN_REPAIR, 'started_at' => now()]);
        $this->expectException(ValidationException::class);
        app(AssetRepairLifecycleService::class)->completeRepair($repair, $user, 'Diagnosis', 'Notes');
    }

    public function test_invalid_work_order_state_is_rejected(): void
    {
        [$repair, $user] = $this->approvedRepair('STATE');
        $workOrder = app(AssetRepairWorkOrderService::class)->createForRepair($repair, $user);
        $workOrder->update(['status' => AssetRepairWorkOrder::STATUS_CANCELLED]);
        $this->expectException(ValidationException::class);
        app(AssetRepairLifecycleService::class)->startRepair($repair, $user);
    }

    public function test_unrepairable_outcome_does_not_destroy_or_deactivate_asset(): void
    {
        [$repair, $user] = $this->approvedRepair('SAFE');
        $assetId = $repair->asset_id;
        $before = Asset::withoutGlobalScopes()->findOrFail($assetId);
        $repair = app(AssetRepairLifecycleService::class)->startRepair($repair, $user);
        app(AssetRepairLifecycleService::class)->completeRepair(
            $repair, $user, 'Beyond economical repair', 'Recorded for separate disposition workflow', null,
            AssetRepairWorkOrder::OUTCOME_UNREPAIRABLE
        );
        $after = Asset::withoutGlobalScopes()->findOrFail($assetId);
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->is_active, $after->is_active);
    }

    public function test_cross_tenant_actor_cannot_start_work_order_lifecycle(): void
    {
        [$repair] = $this->approvedRepair('TEN-A');
        [, $otherUser] = $this->actors('TEN-B');
        $this->expectException(ValidationException::class);
        app(AssetRepairLifecycleService::class)->startRepair($repair, $otherUser);
    }

    public function test_failed_start_does_not_leave_an_orphan_work_order(): void
    {
        [$repair, $user] = $this->approvedRepair('ATOMIC-START');

        $repair->workflowInstance()->withoutGlobalScopes()->update([
            'status' => 'active',
        ]);

        try {
            app(AssetRepairLifecycleService::class)->startRepair(
                repairRequest: $repair->fresh(),
                actor: $user,
                repairType: AssetRepairWorkOrder::TYPE_EXTERNAL,
                externalProviderName: 'V6.3 Provider'
            );

            $this->fail('Expected incomplete workflow validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow', $exception->errors());
        }

        $this->assertFalse(
            AssetRepairWorkOrder::withoutGlobalScopes()
                ->where('asset_repair_request_id', $repair->id)
                ->exists()
        );
        $this->assertSame(
            AssetRepairRequest::STATUS_APPROVED,
            $repair->fresh()->status
        );
    }

    public function test_failed_completion_rolls_back_component_cost_updates(): void
    {
        [$repair, $user] = $this->approvedRepair('ATOMIC-COMPLETE');
        $repair = app(AssetRepairLifecycleService::class)->startRepair($repair, $user);

        try {
            app(AssetRepairLifecycleService::class)->completeRepairWithCosts(
                repairRequest: $repair,
                actor: $user,
                diagnosis: 'Power fault',
                repairNotes: 'Attempted repair',
                laborCost: 100,
                partsCost: 200,
                externalServiceCost: 300,
                outcome: 'invalid-outcome'
            );

            $this->fail('Expected outcome validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('outcome', $exception->errors());
        }

        $workOrder = $repair->workOrder()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame(AssetRepairWorkOrder::STATUS_IN_PROGRESS, $workOrder->status);
        $this->assertSame('0.00', $workOrder->labor_cost);
        $this->assertSame('0.00', $workOrder->parts_cost);
        $this->assertSame('0.00', $workOrder->external_service_cost);
        $this->assertSame(AssetRepairRequest::STATUS_IN_REPAIR, $repair->fresh()->status);
        $this->assertFalse(
            AuditLog::withoutGlobalScopes()
                ->where('subject_type', AssetRepairRequest::class)
                ->where('subject_id', $repair->id)
                ->whereIn('action', [
                    'asset_repair.costs_updated',
                    'asset_repair.completed',
                ])
                ->exists()
        );
    }

    public function test_repair_lifecycle_writes_tenant_and_actor_scoped_audit_trail(): void
    {
        [$repair, $user] = $this->approvedRepair('AUDIT');
        $repair = app(AssetRepairLifecycleService::class)->startRepair(
            repairRequest: $repair,
            actor: $user,
            repairType: AssetRepairWorkOrder::TYPE_EXTERNAL,
            externalProviderName: 'V6.4 Provider'
        );

        $repair = app(AssetRepairLifecycleService::class)->completeRepairWithCosts(
            repairRequest: $repair,
            actor: $user,
            diagnosis: 'Power supply failure',
            repairNotes: 'Replaced and verified',
            laborCost: 100,
            partsCost: 200,
            externalServiceCost: 300,
            outcome: AssetRepairWorkOrder::OUTCOME_REPAIRED
        );

        $logs = AuditLog::withoutGlobalScopes()
            ->where('subject_type', AssetRepairRequest::class)
            ->where('subject_id', $repair->id)
            ->orderBy('id')
            ->get();

        $this->assertSame([
            'asset_repair.created',
            'asset_repair.submitted',
            'asset_repair.started',
            'asset_repair.costs_updated',
            'asset_repair.completed',
        ], $logs->pluck('action')->all());

        foreach ($logs as $log) {
            $this->assertSame($repair->company_id, $log->company_id);
            $this->assertSame($user->id, $log->user_id);
        }

        $started = $logs->firstWhere('action', 'asset_repair.started');
        $completed = $logs->firstWhere('action', 'asset_repair.completed');

        $this->assertSame('approved', $started->old_values['status']);
        $this->assertSame('in_repair', $started->new_values['status']);
        $this->assertSame('external', $started->new_values['repair_type']);
        $this->assertSame('completed', $completed->new_values['status']);
        $this->assertSame('repaired', $completed->new_values['outcome']);
        $this->assertSame('600.00', $completed->new_values['actual_cost']);
    }

    public function test_in_progress_repair_can_be_cancelled_and_reopened_atomically(): void
    {
        [$repair, $user] = $this->approvedRepair('CANCEL-REOPEN');
        $service = app(AssetRepairLifecycleService::class);
        $repair = $service->startRepair($repair, $user);
        $repair = $service->cancelRepair($repair, $user, 'Waiting for replacement part');

        $this->assertSame(AssetRepairRequest::STATUS_CANCELLED, $repair->status);
        $this->assertSame(AssetRepairRequest::STATUS_IN_REPAIR, $repair->cancelled_from_status);
        $this->assertSame(AssetRepairWorkOrder::STATUS_CANCELLED, $repair->workOrder->status);
        $this->assertNotNull($repair->cancelled_at);

        try {
            $service->completeRepair($repair, $user, 'Invalid', 'Must stay cancelled');
            $this->fail('Cancelled repair must not be completed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('repair_request', $exception->errors());
        }

        $repair = $service->reopenRepair($repair, $user, 'Replacement part received');
        $this->assertSame(AssetRepairRequest::STATUS_IN_REPAIR, $repair->status);
        $this->assertSame(AssetRepairWorkOrder::STATUS_IN_PROGRESS, $repair->workOrder->status);
        $this->assertNotNull($repair->reopened_at);
        $this->assertSame('Replacement part received', $repair->reopen_reason);

        $actions = AuditLog::withoutGlobalScopes()
            ->where('subject_type', AssetRepairRequest::class)
            ->where('subject_id', $repair->id)
            ->pluck('action');
        $this->assertTrue($actions->contains('asset_repair.cancelled'));
        $this->assertTrue($actions->contains('asset_repair.reopened'));
    }

    public function test_cancel_rejects_cross_tenant_actor_and_terminal_state(): void
    {
        [$repair, $user] = $this->approvedRepair('CANCEL-GUARD');
        [, $otherUser] = $this->actors('CANCEL-OTHER');
        $service = app(AssetRepairLifecycleService::class);

        try {
            $service->cancelRepair($repair, $otherUser, 'Unauthorized');
            $this->fail('Expected cross-tenant cancellation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('actor', $exception->errors());
        }

        $repair = $service->startRepair($repair, $user);
        $repair = $service->completeRepair($repair, $user, 'Fixed', 'Verified');

        $this->expectException(ValidationException::class);
        $service->cancelRepair($repair, $user, 'Too late');
    }

    private function approvedRepair(string $suffix): array
    {
        [$company, $user, $employee] = $this->actors($suffix);
        $category = AssetCategory::withoutGlobalScopes()->where('is_active', true)->orderBy('id')->firstOrFail();
        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'asset_category_id' => $category->id,
            'inventory_code' => 'V61-INV-'.uniqid(), 'asset_code' => 'V61-'.uniqid(),
            'title' => 'V6.1 repair asset', 'status' => 'warehouse', 'is_active' => true,
        ]);
        $workflow = Workflow::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => 'V6.1 Workflow '.$suffix,
            'code' => 'V61-WF-'.strtoupper(substr(uniqid(), -8)), 'process_type' => 'asset_repair',
            'is_active' => true, 'is_default' => true, 'version' => 1,
        ]);
        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id, 'name' => 'Approval', 'code' => 'V61-APPROVAL',
            'step_type' => 'approval', 'approver_type' => 'requester', 'sort_order' => 10,
            'is_required' => true, 'is_active' => true, 'rejection_action' => 'terminate',
        ]);
        $service = app(AssetRepairRequestService::class);
        $repair = $service->createDraft($asset, $user, $employee, 'V6.1 '.$suffix, 'Integration test', AssetRepairRequest::PRIORITY_HIGH, 1000);
        $repair = $service->submit($repair);
        app(WorkflowRuntimeService::class)->approve($repair->workflowInstance, $employee, $user);

        return [$repair->fresh(), $user];
    }

    private function actors(string $suffix): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'V6.1 Company '.$suffix.uniqid(),
            'code' => 'V61-'.strtoupper(substr(uniqid(), -8)), 'is_active' => true,
        ]);
        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => 'V6.1 User',
            'username' => 'v61-'.uniqid(), 'email' => 'v61-'.uniqid().'@example.test',
            'password' => bcrypt('secret'), 'is_active' => true, 'is_super_admin' => false,
        ]);
        $employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'personnel_code' => 'V61E-'.strtoupper(substr(uniqid(), -8)),
            'display_name' => 'V6.1 Employee', 'is_active' => true,
        ]);

        return [$company, $user, $employee];
    }
}
