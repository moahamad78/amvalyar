<?php

declare(strict_types=1);

namespace Tests\Feature\Stocktake;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StocktakeFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stocktake_tables_have_core_foundation_columns(): void
    {
        $this->assertTrue(Schema::hasTable('stocktakes'));
        $this->assertTrue(Schema::hasTable('stocktake_items'));

        foreach ([
            'company_id',
            'code',
            'title',
            'scope_type',
            'site_id',
            'department_id',
            'location_id',
            'status',
            'planned_date',
            'started_at',
            'completed_at',
            'created_by',
            'completed_by',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('stocktakes', $column),
                'Missing stocktakes.' . $column
            );
        }

        foreach ([
            'company_id',
            'stocktake_id',
            'asset_id',
            'expected_custody_type',
            'expected_site_id',
            'expected_location_id',
            'result_status',
            'observed_custody_type',
            'observed_site_id',
            'observed_location_id',
            'counted_by',
            'counted_at',
            'count_round',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('stocktake_items', $column),
                'Missing stocktake_items.' . $column
            );
        }
    }

    public function test_stocktake_can_snapshot_an_asset_without_mutating_asset(): void
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Stocktake Foundation ' . uniqid(),
            'code' => 'STK-' . strtoupper(substr(uniqid(), -8)),
            'is_active' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Stocktake User',
            'username' => 'stocktake-' . uniqid(),
            'email' => 'stocktake-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $category = AssetCategory::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'INV-' . uniqid(),
            'asset_code' => 'STK-ASSET-' . uniqid(),
            'title' => 'Stocktake Asset',
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'is_active' => true,
        ]);

        $stocktake = Stocktake::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'code' => 'COUNT-' . strtoupper(substr(uniqid(), -8)),
            'title' => 'Foundation Count',
            'scope_type' => Stocktake::SCOPE_COMPANY,
            'status' => Stocktake::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $item = StocktakeItem::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'stocktake_id' => $stocktake->id,
            'asset_id' => $asset->id,
            'expected_status' => $asset->status,
            'expected_custody_type' => $asset->custody_type,
            'expected_site_id' => $asset->current_site_id,
            'expected_location_id' => $asset->current_location_id,
            'result_status' => StocktakeItem::RESULT_PENDING,
        ]);

        $asset->refresh();

        $this->assertSame('warehouse', $asset->status);
        $this->assertSame('warehouse', $asset->custody_type);
        $this->assertSame((int) $asset->id, (int) $item->asset_id);
        $this->assertSame('warehouse', $item->expected_status);
        $this->assertSame('warehouse', $item->expected_custody_type);
        $this->assertSame(StocktakeItem::RESULT_PENDING, $item->result_status);
    }
}