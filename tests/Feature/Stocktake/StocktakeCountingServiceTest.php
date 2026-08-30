<?php

declare(strict_types=1);

namespace Tests\Feature\Stocktake;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Location;
use App\Models\Site;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use App\Services\StocktakeCountingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class StocktakeCountingServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_observation_classifies_matched_misplaced_custody_mismatch_and_damaged(): void
    {
        [$company, $user, $asset, $stocktake] = $this->fixture();

        $site = Site::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Observed Site',
            'code' => 'OBS-S-' . strtoupper(substr(uniqid(), -6)),
            'type' => 'factory',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $location = Location::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'name' => 'Observed Location',
            'code' => 'OBS-L-' . strtoupper(substr(uniqid(), -6)),
            'type' => 'room',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $service = app(StocktakeCountingService::class);

        $matched = $service->observe($stocktake, $asset, $user);
        $this->assertSame(
            StocktakeItem::RESULT_MATCHED,
            $matched->result_status
        );
        $this->assertSame(1, $matched->count_round);

        $misplaced = $service->observe(
            $stocktake,
            $asset,
            $user,
            [
                'site_id' => $site->id,
                'location_id' => $location->id,
            ]
        );

        $this->assertSame(
            StocktakeItem::RESULT_MISPLACED,
            $misplaced->result_status
        );
        $this->assertSame(2, $misplaced->count_round);

        $custody = $service->observe(
            $stocktake,
            $asset,
            $user,
            [
                'custody_type' => 'employee',
                'user_id' => $user->id,
            ]
        );

        $this->assertSame(
            StocktakeItem::RESULT_CUSTODY_MISMATCH,
            $custody->result_status
        );

        $damaged = $service->observe(
            $stocktake,
            $asset,
            $user,
            [
                'damaged' => true,
                'notes' => 'Physical damage',
            ]
        );

        $this->assertSame(
            StocktakeItem::RESULT_DAMAGED,
            $damaged->result_status
        );
        $this->assertSame('Physical damage', $damaged->notes);

        $asset->refresh();

        $this->assertSame('warehouse', $asset->status);
        $this->assertSame('warehouse', $asset->custody_type);
    }

    public function test_expected_item_can_be_marked_missing_without_mutating_asset(): void
    {
        [, $user, $asset, $stocktake] = $this->fixture();

        $item = app(StocktakeCountingService::class)
            ->markMissing(
                $stocktake,
                $asset,
                $user,
                'Not found'
            );

        $this->assertSame(
            StocktakeItem::RESULT_MISSING,
            $item->result_status
        );
        $this->assertSame(1, $item->count_round);
        $this->assertSame('Not found', $item->notes);

        $asset->refresh();
        $this->assertSame('warehouse', $asset->status);
    }

    public function test_asset_outside_frozen_snapshot_is_rejected(): void
    {
        [$company, $user, , $stocktake] = $this->fixture();

        $outside = $this->asset($company);

        $this->expectException(ValidationException::class);

        app(StocktakeCountingService::class)
            ->observe($stocktake, $outside, $user);
    }

    public function test_completed_stocktake_cannot_be_counted(): void
    {
        [, $user, $asset, $stocktake] = $this->fixture();

        $stocktake->forceFill([
            'status' => Stocktake::STATUS_COMPLETED,
        ])->save();

        $this->expectException(ValidationException::class);

        app(StocktakeCountingService::class)
            ->observe($stocktake->fresh(), $asset, $user);
    }

    private function fixture(): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Count ' . uniqid(),
            'code' => 'CT-' . strtoupper(substr(uniqid(), -7)),
            'is_active' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Counter',
            'username' => 'counter-' . uniqid(),
            'email' => 'counter-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $asset = $this->asset($company);

        $stocktake = Stocktake::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'code' => 'COUNT-' . strtoupper(substr(uniqid(), -7)),
            'title' => 'Counting Test',
            'scope_type' => Stocktake::SCOPE_COMPANY,
            'status' => Stocktake::STATUS_ACTIVE,
            'started_at' => now(),
            'created_by' => $user->id,
        ]);

        StocktakeItem::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'stocktake_id' => $stocktake->id,
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

        return [$company, $user, $asset, $stocktake];
    }

    private function asset(Company $company): Asset
    {
        $category = AssetCategory::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        return Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'INV-' . uniqid(),
            'asset_code' => 'COUNT-' . uniqid(),
            'title' => 'Count Asset',
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'is_active' => true,
        ]);
    }
}