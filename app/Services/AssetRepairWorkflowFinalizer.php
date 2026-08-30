<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetRepairRequest;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetRepairWorkflowFinalizer
{
    public function finalizeCompleted(WorkflowInstance $instance): void
    {
        if ($instance->subject_type !== AssetRepairRequest::class) {
            return;
        }

        $this->synchronize($instance, 'completed');
    }

    public function finalizeRejected(WorkflowInstance $instance): void
    {
        if ($instance->subject_type !== AssetRepairRequest::class) {
            return;
        }

        $this->synchronize($instance, 'rejected');
    }

    private function synchronize(WorkflowInstance $instance, string $outcome): void
    {
        DB::transaction(function () use ($instance, $outcome): void {
            $lockedInstance = WorkflowInstance::withoutGlobalScopes()
                ->whereKey($instance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInstance->subject_id === null) {
                throw ValidationException::withMessages([
                    'repair_request' => 'Repair workflow subject is missing.',
                ]);
            }

            if ($lockedInstance->status !== $outcome || $lockedInstance->current_step_id !== null) {
                throw ValidationException::withMessages([
                    'workflow' => 'Repair workflow outcome is not final.',
                ]);
            }

            $repair = AssetRepairRequest::withoutGlobalScopes()
                ->whereKey($lockedInstance->subject_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $repair->company_id !== (int) $lockedInstance->company_id) {
                throw ValidationException::withMessages([
                    'repair_request' => 'Repair request company does not match workflow company.',
                ]);
            }

            if (
                $repair->workflow_instance_id === null
                || (int) $repair->workflow_instance_id !== (int) $lockedInstance->id
            ) {
                throw ValidationException::withMessages([
                    'repair_request' => 'Repair request workflow link is invalid.',
                ]);
            }

            if ($outcome === 'completed') {
                if ($repair->status === AssetRepairRequest::STATUS_APPROVED) {
                    return;
                }

                if ($repair->status !== AssetRepairRequest::STATUS_SUBMITTED) {
                    throw ValidationException::withMessages([
                        'repair_request' => 'Only submitted repair requests can become approved.',
                    ]);
                }

                $repair->update([
                    'status' => AssetRepairRequest::STATUS_APPROVED,
                ]);

                return;
            }

            if ($repair->status === AssetRepairRequest::STATUS_REJECTED) {
                return;
            }

            if (!in_array($repair->status, [
                AssetRepairRequest::STATUS_SUBMITTED,
                AssetRepairRequest::STATUS_IN_REVIEW,
            ], true)) {
                throw ValidationException::withMessages([
                    'repair_request' => 'Repair request cannot be rejected from its current state.',
                ]);
            }

            $repair->update([
                'status' => AssetRepairRequest::STATUS_REJECTED,
            ]);
        });
    }
}