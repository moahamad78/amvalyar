<?php

declare(strict_types=1);

namespace Tests\Feature\AssetMovement;

use App\Models\Asset;
use App\Models\AssetMovementRequest;
use App\Models\AssetCategory;
use App\Models\AssetTransaction;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\AssetMovementFinalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssetMovementLifecycleEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transfer_finalization_moves_custody_once_and_is_idempotent(): void
    {
        [$company, $holder, $holderEmployee, $target, $targetEmployee] =
            $this->movementActors();

        $asset = $this->asset($company, 'assigned', 'E2E-MOVE-TR-001');
        $asset->forceFill([
            'custody_type' => 'employee',
            'custody_user_id' => $holder->id,
            'custody_employee_id' => $holderEmployee->id,
        ])->save();

        $this->delivery($asset, $holder);

        [$request, $instance] = $this->completedMovement(
            $asset,
            AssetMovementRequest::TYPE_TRANSFER,
            $holder,
            $holderEmployee,
            $target
        );

        $before = AssetTransaction::withoutGlobalScopes()
            ->where('asset_id', $asset->id)
            ->count();

        app(AssetMovementFinalizer::class)->finalizeCompleted(
            $instance,
            $holderEmployee,
            $holder
        );

        $asset->refresh();
        $request->refresh();

        $this->assertSame('assigned', $asset->status);
        $this->assertSame('completed', $request->status);
        $this->assertNotNull($request->completed_at);

        $transaction = AssetTransaction::withoutGlobalScopes()
            ->where('asset_movement_request_id', $request->id)
            ->firstOrFail();

        $this->assertSame('transfer', $transaction->type);
        $this->assertSame((int) $holder->id, (int) $transaction->from_user_id);
        $this->assertSame((int) $target->id, (int) $transaction->to_user_id);
        $this->assertSame($asset->asset_code, $transaction->plate_number);

        $afterFirst = AssetTransaction::withoutGlobalScopes()
            ->where('asset_id', $asset->id)
            ->count();

        $this->assertSame($before + 1, $afterFirst);

        app(AssetMovementFinalizer::class)->finalizeCompleted(
            $instance->fresh(),
            $holderEmployee,
            $holder
        );

        $afterSecond = AssetTransaction::withoutGlobalScopes()
            ->where('asset_id', $asset->id)
            ->count();

        $this->assertSame($afterFirst, $afterSecond);
    }

    public function test_return_finalization_returns_asset_to_warehouse_and_records_holder(): void
    {
        [$company, $holder, $holderEmployee] =
            $this->movementActors();

        $asset = $this->asset($company, 'assigned', 'E2E-MOVE-RT-001');
        $asset->forceFill([
            'custody_type' => 'employee',
            'custody_user_id' => $holder->id,
            'custody_employee_id' => $holderEmployee->id,
        ])->save();

        $this->delivery($asset, $holder);

        [$request, $instance] = $this->completedMovement(
            $asset,
            AssetMovementRequest::TYPE_RETURN,
            $holder,
            $holderEmployee
        );

        app(AssetMovementFinalizer::class)->finalizeCompleted(
            $instance,
            $holderEmployee,
            $holder
        );

        $asset->refresh();
        $request->refresh();

        $this->assertSame('warehouse', $asset->status);
        $this->assertSame('completed', $request->status);

        $transaction = AssetTransaction::withoutGlobalScopes()
            ->where('asset_movement_request_id', $request->id)
            ->firstOrFail();

        $this->assertSame('return', $transaction->type);
        $this->assertSame((int) $holder->id, (int) $transaction->from_user_id);
        $this->assertNull($transaction->to_user_id);
    }

    public function test_transfer_can_finalize_to_active_employee_without_login_account(): void
    {
        [$company, $holder, $holderEmployee] = $this->movementActors();

        $recipient = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'personnel_code' => 'MNL-' . strtoupper(substr(uniqid(), -8)),
            'display_name' => 'No Login Recipient',
            'is_active' => true,
        ]);

        $asset = $this->asset($company, 'assigned', 'E2E-MOVE-NL-001');
        $asset->forceFill([
            'custody_type' => 'employee',
            'custody_user_id' => $holder->id,
            'custody_employee_id' => $holderEmployee->id,
        ])->save();
        $this->delivery($asset, $holder);

        [$request, $instance] = $this->completedMovement(
            $asset,
            AssetMovementRequest::TYPE_TRANSFER,
            $holder,
            $holderEmployee,
            null,
            $recipient
        );

        app(AssetMovementFinalizer::class)->finalizeCompleted(
            $instance,
            $holderEmployee,
            $holder
        );

        $asset->refresh();
        $transaction = AssetTransaction::withoutGlobalScopes()
            ->where('asset_movement_request_id', $request->id)
            ->firstOrFail();

        $this->assertSame((int) $recipient->id, (int) $asset->custody_employee_id);
        $this->assertNull($asset->custody_user_id);
        $this->assertSame((int) $recipient->id, (int) $transaction->to_employee_id);
        $this->assertNull($transaction->to_user_id);
    }

    public function test_disposal_finalization_destroys_only_warehouse_asset_and_records_destroy_transaction(): void
    {
        [$company, $requester, $requesterEmployee] =
            $this->movementActors();

        $asset = $this->asset($company, 'warehouse', 'E2E-MOVE-DSP-001');

        [$request, $instance] = $this->completedMovement(
            $asset,
            AssetMovementRequest::TYPE_DISPOSAL,
            $requester,
            $requesterEmployee
        );

        app(AssetMovementFinalizer::class)->finalizeCompleted(
            $instance,
            $requesterEmployee,
            $requester
        );

        $asset->refresh();
        $request->refresh();

        $this->assertSame('destroyed', $asset->status);
        $this->assertSame('completed', $request->status);

        $transaction = AssetTransaction::withoutGlobalScopes()
            ->where('asset_movement_request_id', $request->id)
            ->firstOrFail();

        $this->assertSame('destroy', $transaction->type);
        $this->assertNull($transaction->from_user_id);
        $this->assertNull($transaction->to_user_id);
        $this->assertSame($asset->asset_code, $transaction->plate_number);
    }

    public function test_finalizer_rejects_cross_company_workflow_without_mutating_asset_or_transaction_history(): void
    {
        [$company, $holder, $holderEmployee] =
            $this->movementActors();

        $otherCompany = Company::withoutGlobalScopes()->create([
            'name' => 'Movement E2E Other Company',
            'code' => 'MOVX-' . strtoupper(substr(uniqid(), -8)),
            'is_active' => true,
        ]);

        $asset = $this->asset($company, 'assigned', 'E2E-MOVE-TENANT-001');
        $this->delivery($asset, $holder);

        $request = AssetMovementRequest::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'movement_type' => AssetMovementRequest::TYPE_RETURN,
            'requested_by_user_id' => $holder->id,
            'requested_by_employee_id' => $holderEmployee->id,
            'status' => AssetMovementRequest::STATUS_SUBMITTED,
            'reason' => 'Tenant boundary test',
            'submitted_at' => now(),
        ]);

        $instance = WorkflowInstance::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'workflow_name' => 'Movement E2E Cross Company',
            'workflow_code' => 'MOVE-X-' . strtoupper(substr(uniqid(), -8)),
            'process_type' => 'asset_return',
            'subject_type' => AssetMovementRequest::class,
            'subject_id' => $request->id,
            'status' => 'completed',
            'current_step_id' => null,
        ]);

        $request->forceFill([
            'workflow_instance_id' => $instance->id,
        ])->save();

        $countBefore = AssetTransaction::withoutGlobalScopes()
            ->where('asset_id', $asset->id)
            ->count();

        try {
            app(AssetMovementFinalizer::class)->finalizeCompleted(
                $instance,
                $holderEmployee,
                $holder
            );

            $this->fail('Cross-company workflow must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'movement_request',
                $exception->errors()
            );
        }

        $asset->refresh();
        $request->refresh();

        $this->assertSame('assigned', $asset->status);
        $this->assertSame('submitted', $request->status);

        $countAfter = AssetTransaction::withoutGlobalScopes()
            ->where('asset_id', $asset->id)
            ->count();

        $this->assertSame($countBefore, $countAfter);
    }

    private function movementActors(): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Movement E2E Company ' . uniqid(),
            'code' => 'MOVE-' . strtoupper(substr(uniqid(), -8)),
            'is_active' => true,
        ]);

        $holder = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Movement Holder',
            'username' => 'movement-holder-' . uniqid(),
            'email' => 'movement-holder-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $target = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Movement Target',
            'username' => 'movement-target-' . uniqid(),
            'email' => 'movement-target-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $holderEmployee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $holder->id,
            'personnel_code' => 'MHE-' . strtoupper(substr(uniqid(), -8)),
            'display_name' => 'Movement Holder Employee',
            'is_active' => true,
        ]);

        $targetEmployee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $target->id,
            'personnel_code' => 'MTE-' . strtoupper(substr(uniqid(), -8)),
            'display_name' => 'Movement Target Employee',
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
            'title' => 'Movement E2E Asset',
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function delivery(Asset $asset, User $holder): AssetTransaction
    {
        return AssetTransaction::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'to_user_id' => $holder->id,
            'type' => 'delivery',
            'plate_number' => $asset->asset_code,
            'created_by' => $holder->id,
        ]);
    }

    private function completedMovement(
        Asset $asset,
        string $type,
        User $requester,
        Employee $requesterEmployee,
        ?User $target = null,
        ?Employee $targetEmployee = null
    ): array {
        $request = AssetMovementRequest::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'movement_type' => $type,
            'target_user_id' => $target?->id,
            'target_custody_type' => $targetEmployee !== null ? 'employee' : null,
            'target_employee_id' => $targetEmployee?->id,
            'requested_by_user_id' => $requester->id,
            'requested_by_employee_id' => $requesterEmployee->id,
            'status' => AssetMovementRequest::STATUS_SUBMITTED,
            'reason' => 'Movement E2E finalization',
            'submitted_at' => now(),
        ]);

        $instance = WorkflowInstance::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'workflow_name' => 'Movement E2E ' . $type,
            'workflow_code' => 'MOVE-' . strtoupper($type) . '-' . strtoupper(substr(uniqid(), -8)),
            'process_type' => match ($type) {
                AssetMovementRequest::TYPE_TRANSFER => 'asset_transfer',
                AssetMovementRequest::TYPE_RETURN => 'asset_return',
                AssetMovementRequest::TYPE_DISPOSAL => 'asset_disposal',
                default => throw new \RuntimeException('Unsupported movement type in test fixture.'),
            },
            'subject_type' => AssetMovementRequest::class,
            'subject_id' => $request->id,
            'status' => 'completed',
            'current_step_id' => null,
        ]);

        $request->forceFill([
            'workflow_instance_id' => $instance->id,
        ])->save();

        return [$request->fresh(), $instance->fresh()];
    }
}
