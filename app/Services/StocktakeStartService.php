<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StocktakeStartService
{
    public function start(Stocktake $stocktake, User $actor): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $actor): Stocktake {
            /** @var Stocktake $locked */
            $locked = Stocktake::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($stocktake->id);

            $this->assertCanStart($locked, $actor);

            $assets = $this->eligibleAssets($locked)->get();

            foreach ($assets as $asset) {
                StocktakeItem::withoutGlobalScopes()->create([
                    'company_id' => $locked->company_id,
                    'stocktake_id' => $locked->id,
                    'asset_id' => $asset->id,
                    'expected_status' => $asset->status,
                    'expected_custody_type' => $asset->custody_type,
                    'expected_user_id' => $asset->custody_user_id,
                    'expected_employee_id' => $asset->custody_employee_id,
                    'expected_department_id' => $asset->custody_department_id,
                    'expected_site_id' => $asset->current_site_id,
                    'expected_location_id' => $asset->current_location_id,
                    'result_status' => StocktakeItem::RESULT_PENDING,
                ]);
            }

            $locked->forceFill([
                'status' => Stocktake::STATUS_ACTIVE,
                'started_at' => now(),
            ])->save();

            return $locked->fresh([
                'items',
            ]);
        });
    }

    private function assertCanStart(Stocktake $stocktake, User $actor): void
    {
        if ($stocktake->status !== Stocktake::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'stocktake' => 'Only a draft stocktake can be started.',
            ]);
        }

        if (
            ! $actor->is_super_admin
            && (int) $actor->company_id !== (int) $stocktake->company_id
        ) {
            throw ValidationException::withMessages([
                'stocktake' => 'The stocktake belongs to another company.',
            ]);
        }

        $scopeErrors = match ($stocktake->scope_type) {
            Stocktake::SCOPE_COMPANY => [],
            Stocktake::SCOPE_SITE => $stocktake->site_id
                ? []
                : ['site_id' => 'Site scope requires a site.'],
            Stocktake::SCOPE_DEPARTMENT => $stocktake->department_id
                ? []
                : ['department_id' => 'Department scope requires a department.'],
            Stocktake::SCOPE_LOCATION => $stocktake->location_id
                ? []
                : ['location_id' => 'Location scope requires a location.'],
            default => [
                'scope_type' => 'Unsupported stocktake scope.',
            ],
        };

        if ($scopeErrors !== []) {
            throw ValidationException::withMessages($scopeErrors);
        }

        if (
            $stocktake->site_id
            && ! $stocktake->site()
                ->withoutGlobalScopes()
                ->where('company_id', $stocktake->company_id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'site_id' => 'Selected site does not belong to this company.',
            ]);
        }

        if (
            $stocktake->department_id
            && ! $stocktake->department()
                ->withoutGlobalScopes()
                ->where('company_id', $stocktake->company_id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'department_id' => 'Selected department does not belong to this company.',
            ]);
        }

        if (
            $stocktake->location_id
            && ! $stocktake->location()
                ->withoutGlobalScopes()
                ->where('company_id', $stocktake->company_id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'location_id' => 'Selected location does not belong to this company.',
            ]);
        }
    }

    private function eligibleAssets(Stocktake $stocktake)
    {
        $query = Asset::withoutGlobalScopes()
            ->where('company_id', $stocktake->company_id)
            ->where('is_active', true)
            ->where('status', '!=', 'destroyed');

        return match ($stocktake->scope_type) {
            Stocktake::SCOPE_COMPANY => $query,
            Stocktake::SCOPE_SITE => $query->where(
                'current_site_id',
                $stocktake->site_id
            ),
            Stocktake::SCOPE_DEPARTMENT => $query->where(
                'custody_department_id',
                $stocktake->department_id
            ),
            Stocktake::SCOPE_LOCATION => $query->where(
                'current_location_id',
                $stocktake->location_id
            ),
            default => $query->whereRaw('1 = 0'),
        };
    }
}