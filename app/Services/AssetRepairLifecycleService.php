<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetRepairRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetRepairLifecycleService
{
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

            if (
                $workflow === null
                || $workflow->status !== 'completed'
                || $workflow->current_step_id !== null
            ) {
                throw ValidationException::withMessages([
                    'workflow' => 'Repair approval workflow is not completed.',
                ]);
            }

            $repair->update([
                'status' => AssetRepairRequest::STATUS_IN_REPAIR,
                'started_at' => now(),
            ]);

            return $repair->fresh(['asset', 'workflowInstance']);
        });
    }

    public function completeRepair(
        AssetRepairRequest $repairRequest,
        User $actor,
        string $diagnosis,
        string $repairNotes,
        int|float|string|null $actualCost = null
    ): AssetRepairRequest {
        return DB::transaction(function () use (
            $repairRequest,
            $actor,
            $diagnosis,
            $repairNotes,
            $actualCost
        ): AssetRepairRequest {
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

            $diagnosis = trim($diagnosis);
            $repairNotes = trim($repairNotes);

            if ($diagnosis === '') {
                throw ValidationException::withMessages([
                    'diagnosis' => 'Diagnosis is required to complete a repair.',
                ]);
            }

            if ($repairNotes === '') {
                throw ValidationException::withMessages([
                    'repair_notes' => 'Repair notes are required to complete a repair.',
                ]);
            }

            if ($actualCost !== null && (!is_numeric($actualCost) || (float) $actualCost < 0)) {
                throw ValidationException::withMessages([
                    'actual_cost' => 'Actual repair cost must be zero or greater.',
                ]);
            }

            $repair->update([
                'status' => AssetRepairRequest::STATUS_COMPLETED,
                'diagnosis' => $diagnosis,
                'repair_notes' => $repairNotes,
                'actual_cost' => $actualCost,
                'completed_at' => now(),
            ]);

            return $repair->fresh(['asset', 'workflowInstance']);
        });
    }

    private function ensureActorCompany(AssetRepairRequest $repair, User $actor): void
    {
        if (
            !$actor->isSuperAdmin()
            && (int) $actor->company_id !== (int) $repair->company_id
        ) {
            throw ValidationException::withMessages([
                'actor' => 'Actor does not belong to the repair request company.',
            ]);
        }
    }
}