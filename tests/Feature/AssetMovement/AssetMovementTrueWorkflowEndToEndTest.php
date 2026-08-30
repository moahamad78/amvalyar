<?php

declare(strict_types=1);

namespace Tests\Feature\AssetMovement;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMovementRequest;
use App\Models\AssetTransaction;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\AssetMovementRequestService;
use App\Services\WorkflowRuntimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AssetMovementTrueWorkflowEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transfer_runs_real_request_submit_two_step_approval_and_finalizer(): void
    {
        [$company, $holder, $holderEmployee, $target] =
            $this->movementActors();

        $this->workflow(
            $company,
            'asset_transfer',
            'TRUE-E2E-TRANSFER'
        );

        $asset = $this->asset(
            $company,
            'assigned',
            'TRUE-E2E-TR'
        );

        $this->delivery(
            $asset,
            $holder
        );

        $request = $this->submitRealMovement(
            asset: $asset,
            type: AssetMovementRequest::TYPE_TRANSFER,
            requester: $holder,
            requesterEmployee: $holderEmployee,
            target: $target
        );

        $instance = $request->workflowInstance;
        $this->assertNotNull($instance);
        $this->assertSame('submitted', $request->status);
        $this->assertSame('pending', $instance->status);
        $this->assertNotNull($instance->current_step_id);

        $this->assertSame(
            0,
            AssetTransaction::withoutGlobalScopes()
                ->where('asset_movement_request_id', $request->id)
                ->count()
        );

        $runtime = app(WorkflowRuntimeService::class);

        $instance = $runtime->approve(
            $instance,
            $holderEmployee,
            $holder,
            'First real E2E approval'
        );

        $request->refresh();

        $this->assertSame('pending', $instance->status);
        $this->assertSame('submitted', $request->status);
        $this->assertNotNull($instance->current_step_id);
        $this->assertSame(
            0,
            AssetTransaction::withoutGlobalScopes()
                ->where('asset_movement_request_id', $request->id)
                ->count()
        );

        $instance = $runtime->approve(
            $instance,
            $holderEmployee,
            $holder,
            'Final real E2E approval'
        );

        $asset->refresh();
        $request->refresh();
        $instance->refresh();

        $this->assertSame('completed', $instance->status);
        $this->assertNull($instance->current_step_id);
        $this->assertNotNull($instance->completed_at);
        $this->assertSame('completed', $request->status);
        $this->assertNotNull($request->completed_at);
        $this->assertSame('assigned', $asset->status);

        $transaction = AssetTransaction::withoutGlobalScopes()
            ->where('asset_movement_request_id', $request->id)
            ->firstOrFail();

        $this->assertSame('transfer', $transaction->type);
        $this->assertSame((int) $holder->id, (int) $transaction->from_user_id);
        $this->assertSame((int) $target->id, (int) $transaction->to_user_id);
        $this->assertSame((int) $holder->id, (int) $transaction->created_by);
    }

    public function test_return_runs_real_request_submit_two_step_approval_and_finalizer(): void
    {
        [$company, $holder, $holderEmployee] =
            $this->movementActors();

        $this->workflow(
            $company,
            'asset_return',
            'TRUE-E2E-RETURN'
        );

        $asset = $this->asset(
            $company,
            'assigned',
            'TRUE-E2E-RT'
        );

        $this->delivery(
            $asset,
            $holder
        );

        $request = $this->submitRealMovement(
            asset: $asset,
            type: AssetMovementRequest::TYPE_RETURN,
            requester: $holder,
            requesterEmployee: $holderEmployee
        );

        $instance = $this->approveRealWorkflow(
            $request,
            $holderEmployee,
            $holder
        );

        $asset->refresh();
        $request->refresh();

        $this->assertSame('completed', $instance->status);
        $this->assertSame('completed', $request->status);
        $this->assertSame('warehouse', $asset->status);

        $transaction = AssetTransaction::withoutGlobalScopes()
            ->where('asset_movement_request_id', $request->id)
            ->firstOrFail();

        $this->assertSame('return', $transaction->type);
        $this->assertSame((int) $holder->id, (int) $transaction->from_user_id);
        $this->assertNull($transaction->to_user_id);
        $this->assertSame((int) $holder->id, (int) $transaction->created_by);
    }

    public function test_disposal_runs_real_request_submit_two_step_approval_and_finalizer(): void
    {
        [$company, $requester, $requesterEmployee] =
            $this->movementActors();

        $this->workflow(
            $company,
            'asset_disposal',
            'TRUE-E2E-DISPOSAL'
        );

        $asset = $this->asset(
            $company,
            'warehouse',
            'TRUE-E2E-DSP'
        );

        $request = $this->submitRealMovement(
            asset: $asset,
            type: AssetMovementRequest::TYPE_DISPOSAL,
            requester: $requester,
            requesterEmployee: $requesterEmployee
        );

        $instance = $this->approveRealWorkflow(
            $request,
            $requesterEmployee,
            $requester
        );

        $asset->refresh();
        $request->refresh();

        $this->assertSame('completed', $instance->status);
        $this->assertSame('completed', $request->status);
        $this->assertSame('destroyed', $asset->status);

        $transaction = AssetTransaction::withoutGlobalScopes()
            ->where('asset_movement_request_id', $request->id)
            ->firstOrFail();

        $this->assertSame('destroy', $transaction->type);
        $this->assertNull($transaction->from_user_id);
        $this->assertNull($transaction->to_user_id);
        $this->assertSame((int) $requester->id, (int) $transaction->created_by);
    }

    private function submitRealMovement(
        Asset $asset,
        string $type,
        User $requester,
        Employee $requesterEmployee,
        ?User $target = null
    ): AssetMovementRequest {
        $service = app(AssetMovementRequestService::class);

        $request = $service->createDraft(
            asset: $asset,
            movementType: $type,
            requesterUser: $requester,
            requesterEmployee: $requesterEmployee,
            targetUserId: $target?->id,
            reason: 'True workflow E2E ' . $type,
            notes: 'Created through AssetMovementRequestService'
        );

        $this->assertSame('draft', $request->status);
        $this->assertNull($request->workflow_instance_id);

        $request = $service->submit($request);

        $this->assertSame('submitted', $request->status);
        $this->assertNotNull($request->workflow_instance_id);
        $this->assertNotNull($request->submitted_at);
        $this->assertNotNull($request->workflowInstance);

        return $request;
    }

    private function approveRealWorkflow(
        AssetMovementRequest $request,
        Employee $requesterEmployee,
        User $requester
    ) {
        $runtime = app(WorkflowRuntimeService::class);
        $instance = $request->workflowInstance;

        $this->assertNotNull($instance);
        $this->assertSame('pending', $instance->status);

        $instance = $runtime->approve(
            $instance,
            $requesterEmployee,
            $requester,
            'First true E2E approval'
        );

        $request->refresh();

        $this->assertSame('pending', $instance->status);
        $this->assertSame('submitted', $request->status);
        $this->assertSame(
            0,
            AssetTransaction::withoutGlobalScopes()
                ->where('asset_movement_request_id', $request->id)
                ->count()
        );

        $instance = $runtime->approve(
            $instance,
            $requesterEmployee,
            $requester,
            'Final true E2E approval'
        );

        return $instance->fresh([
            'steps',
            'currentStep',
        ]);
    }

    private function workflow(
        Company $company,
        string $processType,
        string $prefix
    ): Workflow {
        $workflow = Workflow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'True Movement E2E ' . $processType,
            'code' => $prefix . '-' . strtoupper(substr(uniqid(), -8)),
            'process_type' => $processType,
            'description' => 'Two-step requester workflow for real movement lifecycle test',
            'is_active' => true,
            'is_default' => true,
            'version' => 1,
        ]);

        foreach ([10, 20] as $index => $sortOrder) {
            WorkflowStep::query()->create([
                'workflow_id' => $workflow->id,
                'name' => 'Requester approval ' . ($index + 1),
                'code' => 'REQ-' . ($index + 1),
                'step_type' => 'approval',
                'approver_type' => 'requester',
                'approver_reference_id' => null,
                'sort_order' => $sortOrder,
                'is_required' => true,
                'is_active' => true,
                'rejection_action' => 'terminate',
            ]);
        }

        return $workflow->fresh([
            'steps',
        ]);
    }

    private function movementActors(): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'True Movement E2E Company ' . uniqid(),
            'code' => 'TMOV-' . strtoupper(substr(uniqid(), -8)),
            'is_active' => true,
        ]);

        $holder = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'True Movement Holder',
            'username' => 'true-movement-holder-' . uniqid(),
            'email' => 'true-movement-holder-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $target = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'True Movement Target',
            'username' => 'true-movement-target-' . uniqid(),
            'email' => 'true-movement-target-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $holderEmployee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $holder->id,
            'personnel_code' => 'TMH-' . strtoupper(substr(uniqid(), -8)),
            'display_name' => 'True Movement Holder Employee',
            'is_active' => true,
        ]);

        $targetEmployee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $target->id,
            'personnel_code' => 'TMT-' . strtoupper(substr(uniqid(), -8)),
            'display_name' => 'True Movement Target Employee',
            'is_active' => true,
        ]);

        return [
            $company,
            $holder,
            $holderEmployee,
            $target,
            $targetEmployee,
        ];
    }

    private function asset(
        Company $company,
        string $status,
        string $assetCode
    ): Asset {
        $category = AssetCategory::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        return Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'INV-' . uniqid(),
            'asset_code' => $assetCode . '-' . uniqid(),
            'title' => 'True Movement E2E Asset',
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function delivery(
        Asset $asset,
        User $holder
    ): AssetTransaction {
        return AssetTransaction::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'to_user_id' => $holder->id,
            'type' => 'delivery',
            'plate_number' => $asset->asset_code,
            'created_by' => $holder->id,
        ]);
    }
}
