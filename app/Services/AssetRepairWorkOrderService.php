<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetRepairRequest;
use App\Models\AssetRepairWorkOrder;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssetRepairWorkOrderService
{
    public function createForRepair(
        AssetRepairRequest $repairRequest,
        User $actor,
        string $repairType = AssetRepairWorkOrder::TYPE_INTERNAL,
        ?Employee $assignedEmployee = null,
        ?string $externalProviderName = null,
        ?CarbonInterface $expectedReturnAt = null,
        ?string $notes = null
    ): AssetRepairWorkOrder {
        return DB::transaction(function () use (
            $repairRequest,
            $actor,
            $repairType,
            $assignedEmployee,
            $externalProviderName,
            $expectedReturnAt,
            $notes
        ): AssetRepairWorkOrder {
            $repair = AssetRepairRequest::withoutGlobalScopes()
                ->whereKey($repairRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureActorCompany($repair, $actor);

            if (!in_array($repair->status, [
                AssetRepairRequest::STATUS_APPROVED,
                AssetRepairRequest::STATUS_IN_REPAIR,
            ], true)) {
                throw ValidationException::withMessages([
                    'repair_request' => 'Work order requires an approved or in-progress repair request.',
                ]);
            }

            $this->validateRepairType($repairType);

            if ($assignedEmployee !== null) {
                $employee = Employee::withoutGlobalScopes()
                    ->whereKey($assignedEmployee->id)
                    ->firstOrFail();

                if (
                    (int) $employee->company_id !== (int) $repair->company_id
                    || !$employee->is_active
                ) {
                    throw ValidationException::withMessages([
                        'assigned_employee' => 'Assigned employee must be active and belong to the repair company.',
                    ]);
                }
            }

            $provider = trim((string) $externalProviderName);

            if (
                $repairType === AssetRepairWorkOrder::TYPE_EXTERNAL
                && $provider === ''
            ) {
                throw ValidationException::withMessages([
                    'external_provider_name' => 'External repair requires a provider name.',
                ]);
            }

            if (
                $repairType === AssetRepairWorkOrder::TYPE_INTERNAL
                && $provider !== ''
            ) {
                throw ValidationException::withMessages([
                    'external_provider_name' => 'Internal repair cannot define an external provider.',
                ]);
            }

            $existing = AssetRepairWorkOrder::withoutGlobalScopes()
                ->where('asset_repair_request_id', $repair->id)
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'repair_request' => 'This repair request already has a work order.',
                ]);
            }

            $status = $repair->status === AssetRepairRequest::STATUS_IN_REPAIR
                ? AssetRepairWorkOrder::STATUS_IN_PROGRESS
                : AssetRepairWorkOrder::STATUS_PLANNED;

            $receivedAt = $repair->status === AssetRepairRequest::STATUS_IN_REPAIR
                ? ($repair->started_at ?? now())
                : null;

            $workOrder = AssetRepairWorkOrder::withoutGlobalScopes()->create([
                'company_id' => $repair->company_id,
                'asset_repair_request_id' => $repair->id,
                'work_order_number' => $this->nextWorkOrderNumber((int) $repair->company_id),
                'repair_type' => $repairType,
                'assigned_employee_id' => $assignedEmployee?->id,
                'external_provider_name' => $provider !== '' ? $provider : null,
                'status' => $status,
                'received_at' => $receivedAt,
                'expected_return_at' => $expectedReturnAt,
                'notes' => $this->cleanNullableText($notes),
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            return $workOrder->fresh([
                'repairRequest',
                'assignedEmployee',
                'createdByUser',
                'updatedByUser',
            ]);
        });
    }

    private function nextWorkOrderNumber(int $companyId): string
    {
        return sprintf(
            'RWO-%d-%s',
            $companyId,
            Str::upper((string) Str::ulid())
        );
    }

    private function validateRepairType(string $repairType): void
    {
        if (!in_array($repairType, [
            AssetRepairWorkOrder::TYPE_INTERNAL,
            AssetRepairWorkOrder::TYPE_EXTERNAL,
        ], true)) {
            throw ValidationException::withMessages([
                'repair_type' => 'Invalid repair work order type.',
            ]);
        }
    }

    private function ensureActorCompany(
        AssetRepairRequest $repair,
        User $actor
    ): void {
        if (
            !$actor->isSuperAdmin()
            && (int) $actor->company_id !== (int) $repair->company_id
        ) {
            throw ValidationException::withMessages([
                'actor' => 'Actor does not belong to the repair request company.',
            ]);
        }
    }

    private function cleanNullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}