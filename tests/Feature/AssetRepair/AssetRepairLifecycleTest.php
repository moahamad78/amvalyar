<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\AssetRepairLifecycleService;
use App\Services\AssetRepairRequestService;
use App\Services\WorkflowRuntimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssetRepairLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_final_workflow_approval_authorizes_repair_without_completing_operation(): void
    {
        [$company, $user, $employee] = $this->actors('APP');
        $asset = $this->asset($company, 'assigned', 'APP');
        $this->workflow($company, 'terminate', 'APP');

        $repair = $this->submitted($asset, $user, $employee, 'APP');
        $instance = app(WorkflowRuntimeService::class)->approve(
            $repair->workflowInstance,
            $employee,
            $user,
            'Approved for repair'
        );

        $repair->refresh();
        $asset->refresh();

        $this->assertSame('completed', $instance->status);
        $this->assertSame(AssetRepairRequest::STATUS_APPROVED, $repair->status);
        $this->assertNull($repair->started_at);
        $this->assertNull($repair->completed_at);
        $this->assertSame('assigned', $asset->status);
    }

    public function test_terminal_workflow_rejection_marks_repair_rejected(): void
    {
        [$company, $user, $employee] = $this->actors('REJ');
        $asset = $this->asset($company, 'warehouse', 'REJ');
        $this->workflow($company, 'terminate', 'REJ');

        $repair = $this->submitted($asset, $user, $employee, 'REJ');

        $instance = app(WorkflowRuntimeService::class)->reject(
            $repair->workflowInstance,
            $employee,
            $user,
            'Rejected repair request'
        );

        $repair->refresh();

        $this->assertSame('rejected', $instance->status);
        $this->assertSame(AssetRepairRequest::STATUS_REJECTED, $repair->status);
        $this->assertNull($repair->started_at);
        $this->assertNull($repair->completed_at);
    }

    public function test_approved_repair_can_start_then_complete_with_operational_details(): void
    {
        [$company, $user, $employee] = $this->actors('LIFE');
        $asset = $this->asset($company, 'warehouse', 'LIFE');
        $this->workflow($company, 'terminate', 'LIFE');

        $repair = $this->submitted($asset, $user, $employee, 'LIFE');
        app(WorkflowRuntimeService::class)->approve($repair->workflowInstance, $employee, $user);

        $lifecycle = app(AssetRepairLifecycleService::class);
        $repair = $lifecycle->startRepair($repair->fresh(), $user);

        $this->assertSame(AssetRepairRequest::STATUS_IN_REPAIR, $repair->status);
        $this->assertNotNull($repair->started_at);
        $this->assertNull($repair->completed_at);

        $repair = $lifecycle->completeRepair(
            $repair,
            $user,
            'Cooling fan bearing failure',
            'Fan replaced and load test completed',
            1750000
        );

        $this->assertSame(AssetRepairRequest::STATUS_COMPLETED, $repair->status);
        $this->assertSame('Cooling fan bearing failure', $repair->diagnosis);
        $this->assertSame('Fan replaced and load test completed', $repair->repair_notes);
        $this->assertSame('1750000.00', $repair->actual_cost);
        $this->assertNotNull($repair->completed_at);
        $this->assertSame('warehouse', $asset->fresh()->status);
    }

    public function test_repair_cannot_start_before_workflow_approval(): void
    {
        [$company, $user, $employee] = $this->actors('EARLY');
        $asset = $this->asset($company, 'warehouse', 'EARLY');
        $this->workflow($company, 'terminate', 'EARLY');
        $repair = $this->submitted($asset, $user, $employee, 'EARLY');

        $this->expectException(ValidationException::class);
        app(AssetRepairLifecycleService::class)->startRepair($repair, $user);
    }

    public function test_cross_tenant_actor_cannot_start_approved_repair(): void
    {
        [$companyA, $userA, $employeeA] = $this->actors('TEN-A');
        [, $userB] = $this->actors('TEN-B');
        $asset = $this->asset($companyA, 'warehouse', 'TEN');
        $this->workflow($companyA, 'terminate', 'TEN');

        $repair = $this->submitted($asset, $userA, $employeeA, 'TEN');
        app(WorkflowRuntimeService::class)->approve($repair->workflowInstance, $employeeA, $userA);

        $this->expectException(ValidationException::class);
        app(AssetRepairLifecycleService::class)->startRepair($repair->fresh(), $userB);
    }

    private function submitted(
        Asset $asset,
        User $user,
        Employee $employee,
        string $suffix
    ): AssetRepairRequest {
        $service = app(AssetRepairRequestService::class);

        $repair = $service->createDraft(
            asset: $asset,
            requesterUser: $user,
            requesterEmployee: $employee,
            title: 'Repair V3 ' . $suffix,
            problemDescription: 'Lifecycle test problem ' . $suffix,
            priority: AssetRepairRequest::PRIORITY_HIGH,
            estimatedCost: 1000000
        );

        return $service->submit($repair);
    }

    private function actors(string $suffix): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Repair V3 Company ' . $suffix . ' ' . uniqid(),
            'code' => 'RV3-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'is_active' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Repair V3 User ' . $suffix,
            'username' => 'repair-v3-' . strtolower($suffix) . '-' . uniqid(),
            'email' => 'repair-v3-' . strtolower($suffix) . '-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'personnel_code' => 'RV3E-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'display_name' => 'Repair V3 Employee ' . $suffix,
            'is_active' => true,
        ]);

        return [$company, $user, $employee];
    }

    private function asset(Company $company, string $status, string $suffix): Asset
    {
        $category = AssetCategory::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        return Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'RV3-INV-' . uniqid(),
            'asset_code' => 'RV3-' . $suffix . '-' . uniqid(),
            'title' => 'Repair V3 Asset',
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function workflow(
        Company $company,
        string $rejectionAction,
        string $suffix
    ): Workflow {
        $workflow = Workflow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Repair V3 Workflow ' . $suffix,
            'code' => 'RV3-WF-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'process_type' => 'asset_repair',
            'description' => 'Repair V3 lifecycle test',
            'is_active' => true,
            'is_default' => true,
            'version' => 1,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'name' => 'Repair approval',
            'code' => 'RV3-APPROVAL',
            'step_type' => 'approval',
            'approver_type' => 'requester',
            'approver_reference_id' => null,
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'rejection_action' => $rejectionAction,
        ]);

        return $workflow;
    }
}