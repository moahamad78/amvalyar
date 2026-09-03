<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\AssetRepairWorkOrder;
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

    private function approvedRepair(string $suffix): array
    {
        [$company, $user, $employee] = $this->actors($suffix);
        $category = AssetCategory::withoutGlobalScopes()->where('is_active', true)->orderBy('id')->firstOrFail();
        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'asset_category_id' => $category->id,
            'inventory_code' => 'V61-INV-' . uniqid(), 'asset_code' => 'V61-' . uniqid(),
            'title' => 'V6.1 repair asset', 'status' => 'warehouse', 'is_active' => true,
        ]);
        $workflow = Workflow::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => 'V6.1 Workflow ' . $suffix,
            'code' => 'V61-WF-' . strtoupper(substr(uniqid(), -8)), 'process_type' => 'asset_repair',
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
            'name' => 'V6.1 Company ' . $suffix . uniqid(),
            'code' => 'V61-' . strtoupper(substr(uniqid(), -8)), 'is_active' => true,
        ]);
        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => 'V6.1 User',
            'username' => 'v61-' . uniqid(), 'email' => 'v61-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'), 'is_active' => true, 'is_super_admin' => false,
        ]);
        $employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'personnel_code' => 'V61E-' . strtoupper(substr(uniqid(), -8)),
            'display_name' => 'V6.1 Employee', 'is_active' => true,
        ]);
        return [$company, $user, $employee];
    }
}