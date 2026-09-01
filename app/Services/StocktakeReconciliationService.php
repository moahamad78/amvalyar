<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StocktakeReconciliationService
{
    public function applyObserved(StocktakeItem $item, User $actor, ?string $note = null): StocktakeItem
    {
        return DB::transaction(function () use ($item, $actor, $note): StocktakeItem {
            $locked = $this->lockItem($item, $actor);
            $stocktake = $this->completedStocktake($locked);
            $asset = Asset::withoutGlobalScopes()->lockForUpdate()->findOrFail($locked->asset_id);

            if ((int) $asset->company_id !== (int) $stocktake->company_id) {
                throw ValidationException::withMessages(['asset' => 'Asset belongs to another company.']);
            }

            if (! in_array($locked->result_status, [
                StocktakeItem::RESULT_MISPLACED,
                StocktakeItem::RESULT_CUSTODY_MISMATCH,
            ], true)) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'Only location or custody discrepancies can apply observed canonical data.',
                ]);
            }

            $before = $this->snapshot($asset);
            $after = $this->validatedObserved($locked, $asset);

            $asset->forceFill([
                'status' => $after['status'],
                'custody_type' => $after['custody_type'],
                'custody_user_id' => $after['custody_user_id'],
                'custody_employee_id' => $after['custody_employee_id'],
                'custody_department_id' => $after['custody_department_id'],
                'current_site_id' => $after['site_id'],
                'current_location_id' => $after['location_id'],
            ])->save();

            AssetTransaction::withoutGlobalScopes()->create([
                'company_id' => $asset->company_id,
                'asset_id' => $asset->id,
                'from_user_id' => $before['custody_user_id'],
                'to_user_id' => $after['custody_user_id'],
                'type' => 'transfer',
                'plate_number' => $asset->asset_code,
                'description' => 'Stocktake reconciliation: '.$stocktake->code.($note ? ' - '.$note : ''),
                'created_by' => $actor->id,
                'from_custody_type' => $before['custody_type'],
                'to_custody_type' => $after['custody_type'],
                'from_employee_id' => $before['custody_employee_id'],
                'to_employee_id' => $after['custody_employee_id'],
                'from_department_id' => $before['custody_department_id'],
                'to_department_id' => $after['custody_department_id'],
                'from_site_id' => $before['site_id'],
                'to_site_id' => $after['site_id'],
                'from_location_id' => $before['location_id'],
                'to_location_id' => $after['location_id'],
            ]);

            $locked->forceFill([
                'reconciliation_status' => StocktakeItem::RECONCILIATION_APPLIED,
                'reconciliation_action' => 'apply_observed',
                'reconciled_by' => $actor->id,
                'reconciled_at' => now(),
                'reconciliation_note' => $note,
            ])->save();

            return $locked->fresh(['asset', 'reconciler']);
        });
    }

    public function resolveWithoutChange(StocktakeItem $item, User $actor, ?string $note = null): StocktakeItem
    {
        return DB::transaction(function () use ($item, $actor, $note): StocktakeItem {
            $locked = $this->lockItem($item, $actor);
            $this->completedStocktake($locked);

            if (! in_array($locked->result_status, [
                StocktakeItem::RESULT_MISSING,
                StocktakeItem::RESULT_DAMAGED,
                StocktakeItem::RESULT_MISPLACED,
                StocktakeItem::RESULT_CUSTODY_MISMATCH,
            ], true)) {
                throw ValidationException::withMessages([
                    'reconciliation' => 'This stocktake item does not require discrepancy reconciliation.',
                ]);
            }

            $locked->forceFill([
                'reconciliation_status' => StocktakeItem::RECONCILIATION_NO_CHANGE,
                'reconciliation_action' => 'no_canonical_change',
                'reconciled_by' => $actor->id,
                'reconciled_at' => now(),
                'reconciliation_note' => $note,
            ])->save();

            return $locked->fresh(['reconciler']);
        });
    }

    private function lockItem(StocktakeItem $item, User $actor): StocktakeItem
    {
        $locked = StocktakeItem::withoutGlobalScopes()->lockForUpdate()->findOrFail($item->id);

        if (! $actor->is_super_admin && (int) $actor->company_id !== (int) $locked->company_id) {
            throw ValidationException::withMessages(['reconciliation' => 'Stocktake item belongs to another company.']);
        }

        if ($locked->reconciliation_status !== StocktakeItem::RECONCILIATION_PENDING) {
            throw ValidationException::withMessages(['reconciliation' => 'Stocktake item is already reconciled.']);
        }

        return $locked;
    }

    private function completedStocktake(StocktakeItem $item): Stocktake
    {
        $stocktake = Stocktake::withoutGlobalScopes()
            ->where('company_id', $item->company_id)
            ->findOrFail($item->stocktake_id);

        if ($stocktake->status !== Stocktake::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'stocktake' => 'Stocktake must be completed before canonical reconciliation.',
            ]);
        }

        return $stocktake;
    }

    private function validatedObserved(StocktakeItem $item, Asset $asset): array
    {
        $type = $item->observed_custody_type ?? $asset->custody_type;
        $userId = $item->observed_user_id;
        $employeeId = $item->observed_employee_id;
        $departmentId = $item->observed_department_id;
        $siteId = $item->observed_site_id;
        $locationId = $item->observed_location_id;

        $location = $locationId ? Location::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)->where('is_active', true)->findOrFail($locationId) : null;
        $site = $siteId ? Site::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)->where('is_active', true)->findOrFail($siteId) : null;
        $department = $departmentId ? Department::withoutGlobalScopes()
            ->where('company_id', $asset->company_id)->where('is_active', true)->findOrFail($departmentId) : null;

        if ($location && $site && $location->site_id !== null && (int) $location->site_id !== (int) $site->id) {
            throw ValidationException::withMessages(['location_id' => 'Observed location does not belong to observed site.']);
        }
        if ($location && ! $site) {
            $siteId = $location->site_id;
        }

        if ($type === AssetCustodyService::TYPE_WAREHOUSE) {
            if ($userId || $employeeId || $departmentId || $siteId || $locationId) {
                throw ValidationException::withMessages(['reconciliation' => 'Warehouse custody cannot retain holder or location references.']);
            }
            return $this->state('warehouse', $type, null, null, null, null, null);
        }

        if ($type === AssetCustodyService::TYPE_EMPLOYEE) {
            if (! $employeeId) {
                throw ValidationException::withMessages(['employee_id' => 'Observed employee custody requires an employee.']);
            }
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $asset->company_id)->where('is_active', true)->findOrFail($employeeId);
            if ($userId !== null && $employee->user_id !== null && (int) $userId !== (int) $employee->user_id) {
                throw ValidationException::withMessages(['user_id' => 'Observed user does not match observed employee.']);
            }
            return $this->state(
                'assigned',
                $type,
                $employee->user_id ?? $userId,
                $employee->id,
                $departmentId ?? $employee->department_id,
                $siteId ?? $employee->site_id,
                $locationId
            );
        }

        if ($type === AssetCustodyService::TYPE_USER) {
            if (! $userId) {
                throw ValidationException::withMessages(['user_id' => 'Observed user custody requires a user.']);
            }
            User::withoutGlobalScopes()->where('company_id', $asset->company_id)
                ->where('is_active', true)->findOrFail($userId);
            return $this->state('assigned', $type, $userId, null, $departmentId, $siteId, $locationId);
        }

        if ($type === AssetCustodyService::TYPE_ORGANIZATION) {
            if (! $departmentId && ! $siteId && ! $locationId) {
                throw ValidationException::withMessages(['organization' => 'Observed organization custody requires department, site, or location.']);
            }
            return $this->state('assigned', $type, null, null, $departmentId, $siteId, $locationId);
        }

        throw ValidationException::withMessages(['custody_type' => 'Observed custody type is not safe for canonical reconciliation.']);
    }

    private function state(
        string $status,
        string $type,
        ?int $userId,
        ?int $employeeId,
        ?int $departmentId,
        ?int $siteId,
        ?int $locationId
    ): array {
        return [
            'status' => $status,
            'custody_type' => $type,
            'custody_user_id' => $userId,
            'custody_employee_id' => $employeeId,
            'custody_department_id' => $departmentId,
            'site_id' => $siteId,
            'location_id' => $locationId,
        ];
    }

    private function snapshot(Asset $asset): array
    {
        return [
            'custody_type' => $asset->custody_type,
            'custody_user_id' => $asset->custody_user_id,
            'custody_employee_id' => $asset->custody_employee_id,
            'custody_department_id' => $asset->custody_department_id,
            'site_id' => $asset->current_site_id,
            'location_id' => $asset->current_location_id,
        ];
    }
}