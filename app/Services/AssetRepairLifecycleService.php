<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetRepairRequest;
use App\Models\AssetRepairWorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetRepairLifecycleService
{
    public function __construct(
        private readonly AssetRepairWorkOrderService $workOrderService
    ) {}

    public function startRepair(AssetRepairRequest $repairRequest, User $actor): AssetRepairRequest
    {
        return DB::transaction(function () use ($repairRequest, $actor): AssetRepairRequest {
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
                $workOrder = $this->workOrderService->createForRepair($repair, $actor);
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

            return $repair->fresh(['asset', 'workflowInstance', 'workOrder']);
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
            if (!in_array($outcome, [
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
            if ($actualCost !== null && (!is_numeric($actualCost) || (float) $actualCost < 0)) {
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

            // An unrepairable outcome is maintenance history, not disposal authority.
            // Asset status/is_active are intentionally untouched here.
            return $repair->fresh(['asset', 'workflowInstance', 'workOrder']);
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
        if (!$actor->isSuperAdmin() && (int) $actor->company_id !== (int) $repair->company_id) {
            throw ValidationException::withMessages(['actor' => 'Actor does not belong to the repair request company.']);
        }
    }
}