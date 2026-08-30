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
use App\Services\AssetRepairRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssetRepairWorkflowSubmissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_tenant_draft_submits_into_real_asset_repair_workflow(): void
    {
        [$company, $user, $employee] = $this->actors('OK');
        $asset = $this->asset($company, 'warehouse', 'OK');
        $workflow = $this->workflow($company, true, 'OK');

        $service = app(AssetRepairRequestService::class);
        $repair = $service->createDraft(
            asset: $asset,
            requesterUser: $user,
            requesterEmployee: $employee,
            title: 'Replace failed cooling fan',
            problemDescription: 'Fan is noisy and stops intermittently.',
            priority: AssetRepairRequest::PRIORITY_HIGH,
            estimatedCost: 1250000
        );

        $this->assertSame(AssetRepairRequest::STATUS_DRAFT, $repair->status);
        $this->assertNull($repair->workflow_instance_id);

        $repair = $service->submit($repair);
        $instance = $repair->workflowInstance;

        $this->assertNotNull($instance);
        $this->assertSame(AssetRepairRequest::STATUS_SUBMITTED, $repair->status);
        $this->assertNotNull($repair->submitted_at);
        $this->assertSame((int) $workflow->id, (int) $instance->workflow_id);
        $this->assertSame(AssetRepairRequest::class, $instance->subject_type);
        $this->assertSame((int) $repair->id, (int) $instance->subject_id);
        $this->assertSame('pending', $instance->status);
        $this->assertSame('warehouse', $asset->fresh()->status);
    }

    public function test_cross_tenant_asset_requester_is_rejected(): void
    {
        [$companyA] = $this->actors('A');
        [, $userB, $employeeB] = $this->actors('B');
        $assetA = $this->asset($companyA, 'warehouse', 'CROSS');

        $this->expectException(ValidationException::class);

        app(AssetRepairRequestService::class)->createDraft(
            asset: $assetA,
            requesterUser: $userB,
            requesterEmployee: $employeeB,
            title: 'Cross tenant',
            problemDescription: 'Must be rejected.'
        );
    }

    public function test_submit_fails_closed_when_only_other_company_has_repair_workflow(): void
    {
        [$companyA, $userA, $employeeA] = $this->actors('NW-A');
        [$companyB] = $this->actors('NW-B');
        $assetA = $this->asset($companyA, 'assigned', 'NW');
        $this->workflow($companyB, true, 'OTHER');

        $service = app(AssetRepairRequestService::class);
        $repair = $service->createDraft(
            asset: $assetA,
            requesterUser: $userA,
            requesterEmployee: $employeeA,
            title: 'No tenant workflow',
            problemDescription: 'Other company workflow must not be selected.'
        );

        try {
            $service->submit($repair);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $repair->refresh();
            $this->assertSame(AssetRepairRequest::STATUS_DRAFT, $repair->status);
            $this->assertNull($repair->workflow_instance_id);
        }
    }

    public function test_duplicate_submit_is_blocked_and_does_not_create_second_instance(): void
    {
        [$company, $user, $employee] = $this->actors('DUP');
        $asset = $this->asset($company, 'warehouse', 'DUP');
        $this->workflow($company, true, 'DUP');

        $service = app(AssetRepairRequestService::class);
        $repair = $service->createDraft(
            asset: $asset,
            requesterUser: $user,
            requesterEmployee: $employee,
            title: 'Duplicate submit',
            problemDescription: 'Submit only once.'
        );

        $repair = $service->submit($repair);
        $instanceId = $repair->workflow_instance_id;

        try {
            $service->submit($repair);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $repair->refresh();
            $this->assertSame((int) $instanceId, (int) $repair->workflow_instance_id);
            $this->assertSame(AssetRepairRequest::STATUS_SUBMITTED, $repair->status);
        }
    }

    public function test_second_open_repair_for_same_asset_is_blocked(): void
    {
        [$company, $user, $employee] = $this->actors('OPEN');
        $asset = $this->asset($company, 'warehouse', 'OPEN');

        $service = app(AssetRepairRequestService::class);
        $service->createDraft(
            asset: $asset,
            requesterUser: $user,
            requesterEmployee: $employee,
            title: 'First repair',
            problemDescription: 'First open repair.'
        );

        $this->expectException(ValidationException::class);

        $service->createDraft(
            asset: $asset,
            requesterUser: $user,
            requesterEmployee: $employee,
            title: 'Second repair',
            problemDescription: 'Must be blocked.'
        );
    }

    private function actors(string $suffix): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Repair V2 Company ' . $suffix . ' ' . uniqid(),
            'code' => 'RV2-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'is_active' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Repair V2 User ' . $suffix,
            'username' => 'repair-v2-' . strtolower($suffix) . '-' . uniqid(),
            'email' => 'repair-v2-' . strtolower($suffix) . '-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'personnel_code' => 'RV2E-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'display_name' => 'Repair V2 Employee ' . $suffix,
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
            'inventory_code' => 'RV2-INV-' . uniqid(),
            'asset_code' => 'RV2-' . $suffix . '-' . uniqid(),
            'title' => 'Repair V2 Asset',
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function workflow(Company $company, bool $default, string $suffix): Workflow
    {
        $workflow = Workflow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Repair V2 Workflow ' . $suffix,
            'code' => 'RV2-WF-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'process_type' => 'asset_repair',
            'description' => 'Repair V2 workflow submission test',
            'is_active' => true,
            'is_default' => $default,
            'version' => 1,
        ]);

        WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'name' => 'Requester approval',
            'code' => 'RV2-REQ',
            'step_type' => 'approval',
            'approver_type' => 'requester',
            'approver_reference_id' => null,
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'rejection_action' => 'terminate',
        ]);

        return $workflow;
    }
}