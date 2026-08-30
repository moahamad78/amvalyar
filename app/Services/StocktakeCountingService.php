<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StocktakeCountingService
{
    public function observe(
        Stocktake $stocktake,
        Asset $asset,
        User $actor,
        array $observed = []
    ): StocktakeItem {
        return DB::transaction(function () use ($stocktake, $asset, $actor, $observed): StocktakeItem {
            $lockedStocktake = Stocktake::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($stocktake->id);

            if (! in_array(
                $lockedStocktake->status,
                [Stocktake::STATUS_ACTIVE, Stocktake::STATUS_RECOUNT],
                true
            )) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Stocktake is not open for counting.',
                ]);
            }

            if (
                ! $actor->is_super_admin
                && (int) $actor->company_id !== (int) $lockedStocktake->company_id
            ) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Stocktake belongs to another company.',
                ]);
            }

            $lockedAsset = Asset::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($asset->id);

            if ((int) $lockedAsset->company_id !== (int) $lockedStocktake->company_id) {
                throw ValidationException::withMessages([
                    'asset' => 'Asset belongs to another company.',
                ]);
            }

            $item = StocktakeItem::withoutGlobalScopes()
                ->where('stocktake_id', $lockedStocktake->id)
                ->where('asset_id', $lockedAsset->id)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw ValidationException::withMessages([
                    'asset' => 'Asset is outside the frozen stocktake scope.',
                ]);
            }

            $observedCustodyType = array_key_exists('custody_type', $observed)
                ? $observed['custody_type']
                : $lockedAsset->custody_type;

            $observedUserId = array_key_exists('user_id', $observed)
                ? $observed['user_id']
                : $lockedAsset->custody_user_id;

            $observedEmployeeId = array_key_exists('employee_id', $observed)
                ? $observed['employee_id']
                : $lockedAsset->custody_employee_id;

            $observedDepartmentId = array_key_exists('department_id', $observed)
                ? $observed['department_id']
                : $lockedAsset->custody_department_id;

            $observedSiteId = array_key_exists('site_id', $observed)
                ? $observed['site_id']
                : $lockedAsset->current_site_id;

            $observedLocationId = array_key_exists('location_id', $observed)
                ? $observed['location_id']
                : $lockedAsset->current_location_id;

            $result = $this->classify(
                $item,
                $observedCustodyType,
                $observedUserId,
                $observedEmployeeId,
                $observedDepartmentId,
                $observedSiteId,
                $observedLocationId,
                (bool) ($observed['damaged'] ?? false)
            );

            $item->forceFill([
                'result_status' => $result,
                'observed_custody_type' => $observedCustodyType,
                'observed_user_id' => $observedUserId,
                'observed_employee_id' => $observedEmployeeId,
                'observed_department_id' => $observedDepartmentId,
                'observed_site_id' => $observedSiteId,
                'observed_location_id' => $observedLocationId,
                'counted_by' => $actor->id,
                'counted_at' => now(),
                'count_round' => max(1, (int) $item->count_round + 1),
                'notes' => $observed['notes'] ?? $item->notes,
            ])->save();

            return $item->fresh();
        });
    }

    public function markMissing(
        Stocktake $stocktake,
        Asset $asset,
        User $actor,
        ?string $notes = null
    ): StocktakeItem {
        return DB::transaction(function () use ($stocktake, $asset, $actor, $notes): StocktakeItem {
            $lockedStocktake = Stocktake::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($stocktake->id);

            if (! in_array(
                $lockedStocktake->status,
                [Stocktake::STATUS_ACTIVE, Stocktake::STATUS_RECOUNT],
                true
            )) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Stocktake is not open for counting.',
                ]);
            }

            if (
                ! $actor->is_super_admin
                && (int) $actor->company_id !== (int) $lockedStocktake->company_id
            ) {
                throw ValidationException::withMessages([
                    'stocktake' => 'Stocktake belongs to another company.',
                ]);
            }

            $lockedAsset = Asset::withoutGlobalScopes()
                ->findOrFail($asset->id);

            if ((int) $lockedAsset->company_id !== (int) $lockedStocktake->company_id) {
                throw ValidationException::withMessages([
                    'asset' => 'Asset belongs to another company.',
                ]);
            }

            $item = StocktakeItem::withoutGlobalScopes()
                ->where('stocktake_id', $lockedStocktake->id)
                ->where('asset_id', $lockedAsset->id)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw ValidationException::withMessages([
                    'asset' => 'Asset is outside the frozen stocktake scope.',
                ]);
            }

            $item->forceFill([
                'result_status' => StocktakeItem::RESULT_MISSING,
                'counted_by' => $actor->id,
                'counted_at' => now(),
                'count_round' => max(1, (int) $item->count_round + 1),
                'notes' => $notes ?? $item->notes,
            ])->save();

            return $item->fresh();
        });
    }

    private function classify(
        StocktakeItem $item,
        mixed $custodyType,
        mixed $userId,
        mixed $employeeId,
        mixed $departmentId,
        mixed $siteId,
        mixed $locationId,
        bool $damaged
    ): string {
        if ($damaged) {
            return StocktakeItem::RESULT_DAMAGED;
        }

        if (
            $this->id($item->expected_site_id) !== $this->id($siteId)
            || $this->id($item->expected_location_id) !== $this->id($locationId)
        ) {
            return StocktakeItem::RESULT_MISPLACED;
        }

        if (
            $item->expected_custody_type !== $custodyType
            || $this->id($item->expected_user_id) !== $this->id($userId)
            || $this->id($item->expected_employee_id) !== $this->id($employeeId)
            || $this->id($item->expected_department_id) !== $this->id($departmentId)
        ) {
            return StocktakeItem::RESULT_CUSTODY_MISMATCH;
        }

        return StocktakeItem::RESULT_MATCHED;
    }

    private function id(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}