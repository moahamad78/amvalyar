<?php

declare(strict_types=1);

namespace Tests\Feature\Stocktake;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use App\Services\StocktakeCountingService;
use App\Services\StocktakeFinalizationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class StocktakeFinalizationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_items_block_recount_and_completion(): void
    {
        [, $user, , $stocktake] = $this->fixture();

        $service = app(StocktakeFinalizationService::class);

        try {
            $service->requestRecount($stocktake, $user);
            $this->fail('Pending stocktake must not enter recount.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $service->complete($stocktake->fresh(), $user);
    }

    public function test_discrepancies_enter_recount_and_can_be_resolved_then_completed(): void
    {
        [, $user, $asset, $stocktake] = $this->fixture();

        $counting = app(StocktakeCountingService::class);
        $finalization = app(StocktakeFinalizationService::class);

        $missing = $counting->markMissing(
            $stocktake,
            $asset,
            $user,
            'First count: not found'
        );

        $this->assertSame(
            StocktakeItem::RESULT_MISSING,
            $missing->result_status
        );

        $recount = $finalization->requestRecount(
            $stocktake->fresh(),
            $user
        );

        $this->assertSame(
            Stocktake::STATUS_RECOUNT,
            $recount->status
        );

        $recountItem = $recount->items->first();

        $this->assertSame(
            StocktakeItem::RESULT_RECOUNT,
            $recountItem->result_status
        );

        $resolved = $counting->observe(
            $recount,
            $asset,
            $user,
            ['notes' => 'Found during recount']
        );

        $this->assertSame(
            StocktakeItem::RESULT_MATCHED,
            $resolved->result_status
        );
        $this->assertSame(2, $resolved->count_round);

        $completed = $finalization->complete(
            $recount->fresh(),
            $user
        );

        $this->assertSame(
            Stocktake::STATUS_COMPLETED,
            $completed->status
        );
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(
            (int) $user->id,
            (int) $completed->completed_by
        );

        $asset->refresh();

        $this->assertSame('warehouse', $asset->status);
        $this->assertSame('warehouse', $asset->custody_type);
    }

    public function test_resolved_discrepancy_may_be_completed_without_forcing_recount(): void
    {
        [, $user, $asset, $stocktake] = $this->fixture();

        $counting = app(StocktakeCountingService::class);
        $finalization = app(StocktakeFinalizationService::class);

        $counting->markMissing($stocktake, $asset, $user);

        $completed = $finalization->complete(
            $stocktake->fresh(),
            $user
        );

        $this->assertSame(
            Stocktake::STATUS_COMPLETED,
            $completed->status
        );

        $this->assertSame(
            StocktakeItem::RESULT_MISSING,
            $completed->items->first()->result_status
        );
    }

    public function test_completed_stocktake_cannot_be_reopened_or_completed_twice(): void
    {
        [, $user, $asset, $stocktake] = $this->fixture();

        app(StocktakeCountingService::class)
            ->observe($stocktake, $asset, $user);

        $service = app(StocktakeFinalizationService::class);

        $completed = $service->complete(
            $stocktake->fresh(),
            $user
        );

        $this->assertSame(
            Stocktake::STATUS_COMPLETED,
            $completed->status
        );

        try {
            $service->complete($completed, $user);
            $this->fail('Completed stocktake must not complete twice.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);

        $service->requestRecount($completed->fresh(), $user);
    }

    private function fixture(): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Finalize ' . uniqid(),
            'code' => 'SF-' . strtoupper(substr(uniqid(), -7)),
            'is_active' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Stocktake Finalizer',
            'username' => 'sf-' . uniqid(),
            'email' => 'sf-' . uniqid() . '@example.test',
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
            'asset_code' => 'FINAL-' . uniqid(),
            'title' => 'Finalization Asset',
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'is_active' => true,
        ]);

        $stocktake = Stocktake::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'code' => 'COUNT-' . strtoupper(substr(uniqid(), -7)),
            'title' => 'Finalization Test',
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
}