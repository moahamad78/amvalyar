<?php

declare(strict_types=1);

namespace Tests\Feature\Stocktake;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\Site;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use App\Services\StocktakeStartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class StocktakeStartServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_company_scope_starts_and_snapshots_only_active_non_destroyed_company_assets(): void
    {
        [$company, $actor] = $this->companyAndUser('company');

        $expected = $this->asset($company, [
            'title' => 'Expected warehouse asset',
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
        ]);

        $assigned = $this->asset($company, [
            'title' => 'Expected assigned asset',
            'status' => 'assigned',
            'custody_type' => 'employee',
            'custody_user_id' => $actor->id,
        ]);

        $destroyed = $this->asset($company, [
            'title' => 'Destroyed asset',
            'status' => 'destroyed',
            'custody_type' => null,
        ]);

        $inactive = $this->asset($company, [
            'title' => 'Inactive asset',
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'is_active' => false,
        ]);

        [$otherCompany] = $this->companyAndUser('other');
        $other = $this->asset($otherCompany, [
            'title' => 'Other company asset',
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
        ]);

        $stocktake = $this->stocktake(
            $company,
            $actor,
            Stocktake::SCOPE_COMPANY
        );

        $started = app(StocktakeStartService::class)
            ->start($stocktake, $actor);

        $ids = $started->items
            ->pluck('asset_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            collect([$expected->id, $assigned->id])
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all(),
            $ids
        );

        $this->assertNotContains((int) $destroyed->id, $ids);
        $this->assertNotContains((int) $inactive->id, $ids);
        $this->assertNotContains((int) $other->id, $ids);
        $this->assertSame(Stocktake::STATUS_ACTIVE, $started->status);
        $this->assertNotNull($started->started_at);
    }

    public function test_location_scope_snapshots_only_assets_in_selected_location(): void
    {
        [$company, $actor] = $this->companyAndUser('location');

        $site = Site::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Stocktake Site',
            'code' => 'SITE-' . strtoupper(substr(uniqid(), -6)),
            'type' => 'factory',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $locationA = Location::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'name' => 'Location A',
            'code' => 'LOC-A-' . strtoupper(substr(uniqid(), -5)),
            'type' => 'room',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $locationB = Location::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'name' => 'Location B',
            'code' => 'LOC-B-' . strtoupper(substr(uniqid(), -5)),
            'type' => 'room',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $inside = $this->asset($company, [
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'current_site_id' => $site->id,
            'current_location_id' => $locationA->id,
        ]);

        $outside = $this->asset($company, [
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'current_site_id' => $site->id,
            'current_location_id' => $locationB->id,
        ]);

        $stocktake = $this->stocktake(
            $company,
            $actor,
            Stocktake::SCOPE_LOCATION,
            locationId: $locationA->id
        );

        $started = app(StocktakeStartService::class)
            ->start($stocktake, $actor);

        $this->assertCount(1, $started->items);
        $this->assertSame(
            (int) $inside->id,
            (int) $started->items->first()->asset_id
        );
        $this->assertNotSame(
            (int) $outside->id,
            (int) $started->items->first()->asset_id
        );
        $this->assertSame(
            (int) $locationA->id,
            (int) $started->items->first()->expected_location_id
        );
    }

    public function test_department_scope_snapshots_department_custody_only(): void
    {
        [$company, $actor] = $this->companyAndUser('department');

        $departmentA = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Department A',
            'code' => 'DEP-A-' . strtoupper(substr(uniqid(), -5)),
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $departmentB = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Department B',
            'code' => 'DEP-B-' . strtoupper(substr(uniqid(), -5)),
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $inside = $this->asset($company, [
            'status' => 'assigned',
            'custody_type' => 'department',
            'custody_department_id' => $departmentA->id,
        ]);

        $this->asset($company, [
            'status' => 'assigned',
            'custody_type' => 'department',
            'custody_department_id' => $departmentB->id,
        ]);

        $stocktake = $this->stocktake(
            $company,
            $actor,
            Stocktake::SCOPE_DEPARTMENT,
            departmentId: $departmentA->id
        );

        $started = app(StocktakeStartService::class)
            ->start($stocktake, $actor);

        $this->assertCount(1, $started->items);
        $this->assertSame(
            (int) $inside->id,
            (int) $started->items->first()->asset_id
        );
        $this->assertSame(
            (int) $departmentA->id,
            (int) $started->items->first()->expected_department_id
        );
    }

    public function test_start_is_not_repeatable_and_does_not_mutate_asset(): void
    {
        [$company, $actor] = $this->companyAndUser('repeat');

        $asset = $this->asset($company, [
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
        ]);

        $stocktake = $this->stocktake(
            $company,
            $actor,
            Stocktake::SCOPE_COMPANY
        );

        $service = app(StocktakeStartService::class);
        $service->start($stocktake, $actor);

        $countBefore = StocktakeItem::withoutGlobalScopes()
            ->where('stocktake_id', $stocktake->id)
            ->count();

        try {
            $service->start($stocktake->fresh(), $actor);
            $this->fail('Second start should fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $countAfter = StocktakeItem::withoutGlobalScopes()
            ->where('stocktake_id', $stocktake->id)
            ->count();

        $asset->refresh();

        $this->assertSame($countBefore, $countAfter);
        $this->assertSame('warehouse', $asset->status);
        $this->assertSame('warehouse', $asset->custody_type);
    }

    private function companyAndUser(string $suffix): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Stocktake ' . $suffix . ' ' . uniqid(),
            'code' => 'ST-' . strtoupper(substr(uniqid(), -8)),
            'is_active' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Stocktake Actor',
            'username' => 'stock-' . $suffix . '-' . uniqid(),
            'email' => 'stock-' . $suffix . '-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        return [$company, $user];
    }

    private function stocktake(
        Company $company,
        User $actor,
        string $scope,
        ?int $siteId = null,
        ?int $departmentId = null,
        ?int $locationId = null
    ): Stocktake {
        return Stocktake::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'code' => 'COUNT-' . strtoupper(substr(uniqid(), -8)),
            'title' => 'Snapshot Test',
            'scope_type' => $scope,
            'site_id' => $siteId,
            'department_id' => $departmentId,
            'location_id' => $locationId,
            'status' => Stocktake::STATUS_DRAFT,
            'created_by' => $actor->id,
        ]);
    }

    private function asset(Company $company, array $overrides = []): Asset
    {
        $category = AssetCategory::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        return Asset::withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'INV-' . uniqid(),
            'asset_code' => 'STK-' . uniqid(),
            'title' => 'Stocktake Asset',
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'is_active' => true,
        ], $overrides));
    }
}