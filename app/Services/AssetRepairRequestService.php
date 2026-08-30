<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetRepairRequest;
use App\Models\Employee;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetRepairRequestService
{
    public function __construct(
        private readonly WorkflowRuntimeService $workflowRuntimeService
    ) {
    }

    public function createDraft(
        Asset $asset,
        User $requesterUser,
        ?Employee $requesterEmployee,
        string $title,
        string $problemDescription,
        string $priority = AssetRepairRequest::PRIORITY_NORMAL,
        int|float|string|null $estimatedCost = null
    ): AssetRepairRequest {
        $this->validateRequester($asset, $requesterUser, $requesterEmployee);
        $this->validateAsset($asset);
        $this->validatePriority($priority);
        $this->ensureNoOpenRepair($asset);

        return AssetRepairRequest::withoutGlobalScopes()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'requested_by_user_id' => $requesterUser->id,
            'requested_by_employee_id' => $requesterEmployee?->id,
            'status' => AssetRepairRequest::STATUS_DRAFT,
            'priority' => $priority,
            'title' => trim($title),
            'problem_description' => trim($problemDescription),
            'estimated_cost' => $estimatedCost,
            'reported_at' => now(),
        ]);
    }

    public function submit(AssetRepairRequest $repairRequest): AssetRepairRequest
    {
        return DB::transaction(function () use ($repairRequest): AssetRepairRequest {
            $locked = AssetRepairRequest::withoutGlobalScopes()
                ->whereKey($repairRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== AssetRepairRequest::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'repair_request' => 'Only draft repair requests can be submitted.',
                ]);
            }

            if ($locked->workflow_instance_id !== null) {
                throw ValidationException::withMessages([
                    'workflow' => 'This repair request is already linked to a workflow.',
                ]);
            }

            $asset = Asset::withoutGlobalScopes()
                ->whereKey($locked->asset_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $asset->company_id !== (int) $locked->company_id) {
                throw ValidationException::withMessages([
                    'asset' => 'Asset company does not match repair request company.',
                ]);
            }

            $this->validateAsset($asset);

            $requesterUser = User::withoutGlobalScopes()
                ->findOrFail($locked->requested_by_user_id);

            $requesterEmployee = $locked->requested_by_employee_id !== null
                ? Employee::withoutGlobalScopes()->findOrFail($locked->requested_by_employee_id)
                : null;

            $this->validateRequester($asset, $requesterUser, $requesterEmployee);

            $workflow = Workflow::withoutGlobalScopes()
                ->where('company_id', $locked->company_id)
                ->where('process_type', 'asset_repair')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

            if ($workflow === null) {
                throw ValidationException::withMessages([
                    'workflow' => 'No active asset repair workflow is configured for this company.',
                ]);
            }

            $instance = $this->workflowRuntimeService->start(
                workflow: $workflow,
                requesterEmployee: $requesterEmployee,
                requesterUser: $requesterUser,
                subjectType: AssetRepairRequest::class,
                subjectId: $locked->id,
                context: [
                    'asset_id' => $asset->id,
                    'priority' => $locked->priority,
                    'title' => $locked->title,
                    'estimated_cost' => $locked->estimated_cost,
                ]
            );

            $locked->update([
                'workflow_instance_id' => $instance->id,
                'status' => AssetRepairRequest::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            return $locked->fresh([
                'asset',
                'requesterUser',
                'requesterEmployee',
                'workflowInstance',
            ]);
        });
    }

    private function validateRequester(
        Asset $asset,
        User $requesterUser,
        ?Employee $requesterEmployee
    ): void {
        if (
            !$requesterUser->isSuperAdmin()
            && (int) $requesterUser->company_id !== (int) $asset->company_id
        ) {
            throw ValidationException::withMessages([
                'requester' => 'Requester does not belong to the asset company.',
            ]);
        }

        if (
            $requesterEmployee !== null
            && (int) $requesterEmployee->company_id !== (int) $asset->company_id
        ) {
            throw ValidationException::withMessages([
                'requester_employee' => 'Requester employee does not belong to the asset company.',
            ]);
        }

        if (
            $requesterEmployee !== null
            && $requesterEmployee->user_id !== null
            && (int) $requesterEmployee->user_id !== (int) $requesterUser->id
        ) {
            throw ValidationException::withMessages([
                'requester_employee' => 'Requester employee is not linked to the requester user.',
            ]);
        }
    }

    private function validateAsset(Asset $asset): void
    {
        if (!$asset->is_active || $asset->status === 'destroyed') {
            throw ValidationException::withMessages([
                'asset' => 'Inactive or destroyed assets cannot enter repair workflow.',
            ]);
        }
    }

    private function validatePriority(string $priority): void
    {
        if (!in_array($priority, [
            AssetRepairRequest::PRIORITY_LOW,
            AssetRepairRequest::PRIORITY_NORMAL,
            AssetRepairRequest::PRIORITY_HIGH,
            AssetRepairRequest::PRIORITY_CRITICAL,
        ], true)) {
            throw ValidationException::withMessages([
                'priority' => 'Invalid repair priority.',
            ]);
        }
    }

    private function ensureNoOpenRepair(Asset $asset): void
    {
        $exists = AssetRepairRequest::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)
            ->where('asset_id', $asset->id)
            ->whereIn('status', [
                AssetRepairRequest::STATUS_DRAFT,
                AssetRepairRequest::STATUS_SUBMITTED,
                AssetRepairRequest::STATUS_IN_REVIEW,
                AssetRepairRequest::STATUS_APPROVED,
                AssetRepairRequest::STATUS_IN_REPAIR,
            ])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'asset' => 'An open repair request already exists for this asset.',
            ]);
        }
    }
}