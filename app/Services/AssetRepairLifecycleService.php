<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetRepairRequest;
use App\Models\AssetRepairWorkOrder;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetRepairLifecycleService
{
    public function __construct(
        private readonly AssetRepairWorkOrderService $workOrderService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function startRepair(
        AssetRepairRequest $repairRequest,
        User $actor,
        string $repairType = AssetRepairWorkOrder::TYPE_INTERNAL,
        ?Employee $assignedEmployee = null,
        ?string $externalProviderName = null,
        ?CarbonInterface $expectedReturnAt = null,
        ?string $workOrderNotes = null
    ): AssetRepairRequest {
        return DB::transaction(function () use (
            $repairRequest,
            $actor,
            $repairType,
            $assignedEmployee,
            $externalProviderName,
            $expectedReturnAt,
            $workOrderNotes
        ): AssetRepairRequest {
            $repair = AssetRepairRequest::withoutGlobalScopes()
                ->whereKey($repairRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureActorCompany($repair, $actor);

            if ($repair->status !== AssetRepairRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'repair_request' => 'Only approved repair requests can be started.',
                ]);
            }

            $workflow = $repair->workflowInstance()->withoutGlobalScopes()->first();
            if ($workflow === null || $workflow->status !== 'completed' || $workflow->current_step_id !== null) {
                throw ValidationException::withMessages([
                    'workflow' => 'Repair approval workflow is not completed.',
                ]);
            }

            $workOrder = AssetRepairWorkOrder::withoutGlobalScopes()
                ->where('asset_repair_request_id', $repair->id)
                ->lockForUpdate()
                ->first();

            if ($workOrder === null) {
                $workOrder = $this->workOrderService->createForRepair(
                    repairRequest: $repair,
                    actor: $actor,
                    repairType: $repairType,
                    assignedEmployee: $assignedEmployee,
                    externalProviderName: $externalProviderName,
                    expectedReturnAt: $expectedReturnAt,
                    notes: $workOrderNotes
                );
            }

            $this->ensureWorkOrderCompany($repair, $workOrder);
            if ($workOrder->status !== AssetRepairWorkOrder::STATUS_PLANNED) {
                throw ValidationException::withMessages([
                    'work_order' => 'Only a planned work order can be started.',
                ]);
            }

            $startedAt = now();
            $workOrder->update([
                'status' => AssetRepairWorkOrder::STATUS_IN_PROGRESS,
                'received_at' => $workOrder->received_at ?? $startedAt,
                'updated_by_user_id' => $actor->id,
            ]);
            $repair->update([
                'status' => AssetRepairRequest::STATUS_IN_REPAIR,
                'started_at' => $startedAt,
            ]);

            $this->auditLogService->log(
                action: 'asset_repair.started',
                subject: $repair,
                oldValues: [
                    'status' => AssetRepairRequest::STATUS_APPROVED,
                ],
                newValues: [
                    'status' => $repair->status,
                    'started_at' => $repair->started_at,
                    'work_order_id' => $workOrder->id,
                    'work_order_number' => $workOrder->work_order_number,
                    'repair_type' => $workOrder->repair_type,
                    'assigned_employee_id' => $workOrder->assigned_employee_id,
                    'external_provider_name' => $workOrder->external_provider_name,
                ],
                description: 'Asset repair work started.',
                actor: $actor
            );

            return $repair->fresh(['asset', 'workflowInstance', 'workOrder']);
        });
    }

    public function completeRepairWithCosts(
        AssetRepairRequest $repairRequest,
        User $actor,
        string $diagnosis,
        string $repairNotes,
        int|float|string $laborCost,
        int|float|string $partsCost,
        int|float|string $externalServiceCost,
        string $outcome = AssetRepairWorkOrder::OUTCOME_REPAIRED
    ): AssetRepairRequest {
        return DB::transaction(function () use (
            $repairRequest,
            $actor,
            $diagnosis,
            $repairNotes,
            $laborCost,
            $partsCost,
            $externalServiceCost,
            $outcome
        ): AssetRepairRequest {
            $this->workOrderService->updateCosts(
                repairRequest: $repairRequest,
                actor: $actor,
                laborCost: $laborCost,
                partsCost: $partsCost,
                externalServiceCost: $externalServiceCost
            );

            return $this->completeRepair(
                repairRequest: $repairRequest,
                actor: $actor,
                diagnosis: $diagnosis,
                repairNotes: $repairNotes,
                actualCost: null,
                outcome: $outcome
            );
        });
    }

    public function completeRepair(
        AssetRepairRequest $repairRequest,
        User $actor,
        string $diagnosis,
        string $repairNotes,
        int|float|string|null $actualCost = null,
        string $outcome = AssetRepairWorkOrder::OUTCOME_REPAIRED
    ): AssetRepairRequest {
        return DB::transaction(function () use ($repairRequest, $actor, $diagnosis, $repairNotes, $actualCost, $outcome): AssetRepairRequest {
            $repair = AssetRepairRequest::withoutGlobalScopes()
                ->whereKey($repairRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureActorCompany($repair, $actor);

            if ($repair->status !== AssetRepairRequest::STATUS_IN_REPAIR) {
                throw ValidationException::withMessages([
                    'repair_request' => 'Only repairs currently in progress can be completed.',
                ]);
            }

            $workOrder = AssetRepairWorkOrder::withoutGlobalScopes()
                ->where('asset_repair_request_id', $repair->id)
                ->lockForUpdate()
                ->first();
            if ($workOrder === null) {
                throw ValidationException::withMessages([
                    'work_order' => 'A repair work order is required before completion.',
                ]);
            }
            $this->ensureWorkOrderCompany($repair, $workOrder);
            if ($workOrder->status !== AssetRepairWorkOrder::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'work_order' => 'Only an in-progress work order can be completed.',
                ]);
            }
            if (! in_array($outcome, [
                AssetRepairWorkOrder::OUTCOME_REPAIRED,
                AssetRepairWorkOrder::OUTCOME_PARTIALLY_REPAIRED,
                AssetRepairWorkOrder::OUTCOME_UNREPAIRABLE,
            ], true)) {
                throw ValidationException::withMessages(['outcome' => 'Invalid completed work order outcome.']);
            }

            $diagnosis = trim($diagnosis);
            $repairNotes = trim($repairNotes);
            if ($diagnosis === '') {
                throw ValidationException::withMessages(['diagnosis' => 'Diagnosis is required to complete a repair.']);
            }
            if ($repairNotes === '') {
                throw ValidationException::withMessages(['repair_notes' => 'Repair notes are required to complete a repair.']);
            }
            if ($actualCost !== null && (! is_numeric($actualCost) || (float) $actualCost < 0)) {
                throw ValidationException::withMessages(['actual_cost' => 'Actual repair cost must be zero or greater.']);
            }

            if ($actualCost !== null) {
                $otherCosts = $workOrder->repair_type === AssetRepairWorkOrder::TYPE_EXTERNAL
                    ? (float) $workOrder->labor_cost + (float) $workOrder->parts_cost
                    : (float) $workOrder->parts_cost + (float) $workOrder->external_service_cost;
                if ((float) $actualCost < $otherCosts) {
                    throw ValidationException::withMessages([
                        'actual_cost' => 'Actual repair cost cannot be less than existing work order component costs.',
                    ]);
                }
                $primaryCostField = $workOrder->repair_type === AssetRepairWorkOrder::TYPE_EXTERNAL
                    ? 'external_service_cost'
                    : 'labor_cost';
                $workOrder->update([
                    $primaryCostField => (float) $actualCost - $otherCosts,
                ]);
            }

            $completedAt = now();
            $workOrder->update([
                'status' => AssetRepairWorkOrder::STATUS_COMPLETED,
                'outcome' => $outcome,
                'actual_return_at' => $completedAt,
                'updated_by_user_id' => $actor->id,
            ]);
            $workOrder->refresh();

            $repair->update([
                'status' => AssetRepairRequest::STATUS_COMPLETED,
                'diagnosis' => $diagnosis,
                'repair_notes' => $repairNotes,
                'actual_cost' => $workOrder->total_cost,
                'completed_at' => $completedAt,
            ]);

            $this->auditLogService->log(
                action: 'asset_repair.completed',
                subject: $repair,
                oldValues: [
                    'status' => AssetRepairRequest::STATUS_IN_REPAIR,
                    'work_order_status' => AssetRepairWorkOrder::STATUS_IN_PROGRESS,
                ],
                newValues: [
                    'status' => $repair->status,
                    'work_order_status' => $workOrder->status,
                    'outcome' => $workOrder->outcome,
                    'actual_cost' => $repair->actual_cost,
                    'completed_at' => $repair->completed_at,
                ],
                description: 'Asset repair work completed.',
                actor: $actor
            );

            // An unrepairable outcome is maintenance history, not disposal authority.
            // Asset status/is_active are intentionally untouched here.
            return $repair->fresh(['asset', 'workflowInstance', 'workOrder']);
        });
    }

    public function cancelRepair(AssetRepairRequest $repairRequest, User $actor, string $reason): AssetRepairRequest
    {
        return DB::transaction(function () use ($repairRequest, $actor, $reason): AssetRepairRequest {
            $repair = AssetRepairRequest::withoutGlobalScopes()->whereKey($repairRequest->id)->lockForUpdate()->firstOrFail();
            $this->ensureActorCompany($repair, $actor);
            $reason = trim($reason);

            if (! in_array($repair->status, [AssetRepairRequest::STATUS_DRAFT, AssetRepairRequest::STATUS_APPROVED, AssetRepairRequest::STATUS_IN_REPAIR], true)) {
                throw ValidationException::withMessages(['repair_request' => 'Repair cannot be cancelled from its current state.']);
            }
            if ($reason === '') {
                throw ValidationException::withMessages(['cancellation_reason' => 'Cancellation reason is required.']);
            }

            $fromStatus = $repair->status;
            $workOrder = AssetRepairWorkOrder::withoutGlobalScopes()->where('asset_repair_request_id', $repair->id)->lockForUpdate()->first();
            if ($workOrder !== null && in_array($workOrder->status, [AssetRepairWorkOrder::STATUS_PLANNED, AssetRepairWorkOrder::STATUS_IN_PROGRESS], true)) {
                $workOrder->update(['status' => AssetRepairWorkOrder::STATUS_CANCELLED, 'updated_by_user_id' => $actor->id]);
            }
            $repair->update([
                'status' => AssetRepairRequest::STATUS_CANCELLED,
                'cancelled_from_status' => $fromStatus,
                'cancellation_reason' => $reason,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now(),
            ]);
            $this->auditLogService->log('asset_repair.cancelled', $repair, ['status' => $fromStatus], ['status' => $repair->status, 'reason' => $reason], 'Asset repair cancelled.', actor: $actor);

            return $repair->fresh(['workOrder']);
        });
    }

    public function reopenRepair(AssetRepairRequest $repairRequest, User $actor, string $reason): AssetRepairRequest
    {
        return DB::transaction(function () use ($repairRequest, $actor, $reason): AssetRepairRequest {
            $repair = AssetRepairRequest::withoutGlobalScopes()->whereKey($repairRequest->id)->lockForUpdate()->firstOrFail();
            $this->ensureActorCompany($repair, $actor);
            $reason = trim($reason);
            $target = $repair->cancelled_from_status;

            if ($repair->status !== AssetRepairRequest::STATUS_CANCELLED || ! in_array($target, [AssetRepairRequest::STATUS_DRAFT, AssetRepairRequest::STATUS_APPROVED, AssetRepairRequest::STATUS_IN_REPAIR], true)) {
                throw ValidationException::withMessages(['repair_request' => 'Repair is not eligible to be reopened.']);
            }
            if ($reason === '') {
                throw ValidationException::withMessages(['reopen_reason' => 'Reopen reason is required.']);
            }

            $workOrder = AssetRepairWorkOrder::withoutGlobalScopes()->where('asset_repair_request_id', $repair->id)->lockForUpdate()->first();
            if ($target === AssetRepairRequest::STATUS_IN_REPAIR && ($workOrder === null || $workOrder->status !== AssetRepairWorkOrder::STATUS_CANCELLED)) {
                throw ValidationException::withMessages(['work_order' => 'Cancelled in-progress repair requires its cancelled work order.']);
            }
            if ($workOrder !== null && $workOrder->status === AssetRepairWorkOrder::STATUS_CANCELLED) {
                $workOrder->update([
                    'status' => $target === AssetRepairRequest::STATUS_IN_REPAIR ? AssetRepairWorkOrder::STATUS_IN_PROGRESS : AssetRepairWorkOrder::STATUS_PLANNED,
                    'updated_by_user_id' => $actor->id,
                ]);
            }
            $repair->update(['status' => $target, 'reopened_at' => now(), 'reopen_reason' => $reason, 'reopened_by_user_id' => $actor->id]);
            $this->auditLogService->log('asset_repair.reopened', $repair, ['status' => AssetRepairRequest::STATUS_CANCELLED], ['status' => $target, 'reason' => $reason], 'Asset repair reopened.', actor: $actor);

            return $repair->fresh(['workOrder']);
        });
    }

    private function ensureWorkOrderCompany(AssetRepairRequest $repair, AssetRepairWorkOrder $workOrder): void
    {
        if ((int) $workOrder->company_id !== (int) $repair->company_id) {
            throw ValidationException::withMessages(['work_order' => 'Work order does not belong to the repair company.']);
        }
    }

    private function ensureActorCompany(AssetRepairRequest $repair, User $actor): void
    {
        if (! $actor->isSuperAdmin() && (int) $actor->company_id !== (int) $repair->company_id) {
            throw ValidationException::withMessages(['actor' => 'Actor does not belong to the repair request company.']);
        }
    }
}
