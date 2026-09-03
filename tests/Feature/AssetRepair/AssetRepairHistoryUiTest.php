<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AssetRepairHistoryUiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_asset_show_contains_repair_history_and_summary_for_same_tenant(): void
    {
        [$company, $user, $asset] = $this->fixture('HISTORY');
        $this->grant($user, 'assets.view', 'asset_repairs.view');

        AssetRepairRequest::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'requested_by_user_id' => $user->id,
            'status' => AssetRepairRequest::STATUS_COMPLETED,
            'priority' => AssetRepairRequest::PRIORITY_HIGH,
            'title' => 'Completed repair history',
            'problem_description' => 'Problem',
            'diagnosis' => 'Diagnosis',
            'repair_notes' => 'Fixed',
            'estimated_cost' => 150000,
            'actual_cost' => 125000,
            'reported_at' => now()->subDays(5),
            'started_at' => now()->subDays(4),
            'completed_at' => now()->subDays(3),
        ]);

        AssetRepairRequest::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'requested_by_user_id' => $user->id,
            'status' => AssetRepairRequest::STATUS_DRAFT,
            'priority' => AssetRepairRequest::PRIORITY_NORMAL,
            'title' => 'Open repair history',
            'problem_description' => 'Another problem',
            'reported_at' => now(),
        ]);

        $this->loginAs($user);

        $response = $this->get(route('assets.show', $asset));

        $response->assertOk();
        $response->assertSee('سوابق تعمیر و نگهداری');
        $response->assertSee('Completed repair history');
        $response->assertSee('Open repair history');
        $response->assertSee('125,000.00');
        $response->assertSee(route('asset-repairs.index', ['asset_id' => $asset->id]), false);
    }

    public function test_repair_workspace_filters_by_asset_status_and_priority(): void
    {
        [$company, $user, $assetA] = $this->fixture('FILTER');
        $this->grant($user, 'asset_repairs.view');

        $category = AssetCategory::query()->firstOrCreate(
            ['name' => 'Repair V5 Filter Category B'],
            ['code' => 'RV5-FILTER-B-' . strtoupper(substr(uniqid(), -5)), 'is_active' => true]
        );

        $assetB = Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'RV5-B-' . strtoupper(substr(uniqid(), -6)),
            'title' => 'Repair V5 Asset B',
            'status' => 'warehouse',
            'is_active' => true,
        ]);

        $this->repair($company, $user, $assetA, 'MATCHED REPAIR', 'completed', 'critical');
        $this->repair($company, $user, $assetA, 'WRONG STATUS', 'draft', 'critical');
        $this->repair($company, $user, $assetB, 'WRONG ASSET', 'completed', 'critical');

        $this->loginAs($user);

        $response = $this->get(route('asset-repairs.index', [
            'asset_id' => $assetA->id,
            'status' => 'completed',
            'priority' => 'critical',
        ]));

        $response->assertOk();
        $response->assertSee('MATCHED REPAIR');
        $response->assertDontSee('WRONG STATUS');
        $response->assertDontSee('WRONG ASSET');
    }

    public function test_cross_tenant_asset_filter_is_hidden_as_not_found(): void
    {
        [, $userA] = $this->fixture('TENANT-A');
        [, , $assetB] = $this->fixture('TENANT-B');
        $this->grant($userA, 'asset_repairs.view');

        $this->loginAs($userA);

        $this->get(route('asset-repairs.index', ['asset_id' => $assetB->id]))
            ->assertNotFound();
    }

    private function fixture(string $suffix): array
    {
        $company = Company::query()->create([
            'name' => 'Repair V5 Company ' . $suffix,
            'code' => 'RV5-' . $suffix . '-' . strtoupper(substr(uniqid(), -5)),
            'is_active' => true,
        ]);

        $role = Role::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Repair V5 Role ' . $suffix,
            'display_name' => 'Repair V5 Role ' . $suffix,
            'code' => 'RV5-ROLE-' . $suffix . '-' . strtoupper(substr(uniqid(), -5)),
            'is_active' => true,
        ]);

        $password = 'RepairV5!123';
        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => 'Repair V5 User ' . $suffix,
            'username' => 'rv5_' . strtolower(str_replace('-', '_', $suffix)) . '_' . strtolower(substr(uniqid(), -6)),
            'email' => strtolower($suffix) . '.' . substr(uniqid(), -6) . '@example.test',
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->setAttribute('plain_test_password', $password);

        $category = AssetCategory::query()->firstOrCreate(
            ['name' => 'Repair V5 Category'],
            ['code' => 'RV5-CAT-' . strtoupper(substr(uniqid(), -5)), 'is_active' => true]
        );

        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'RV5-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'title' => 'Repair V5 Asset ' . $suffix,
            'status' => 'warehouse',
            'is_active' => true,
        ]);

        return [$company, $user, $asset];
    }

    private function grant(User $user, string ...$names): void
    {
        $permissions = Permission::query()->whereIn('name', $names)->get();
        self::assertCount(count($names), $permissions);

        $role = Role::withoutGlobalScopes()->findOrFail($user->role_id);
        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $user->unsetRelation('role');
    }

    private function loginAs(User $user): void
    {
        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => $user->getAttribute('plain_test_password'),
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(session('domain_session_id'));
        $this->assertSame((int) $user->id, (int) session('domain_user_id'));
    }

    private function repair(
        Company $company,
        User $user,
        Asset $asset,
        string $title,
        string $status,
        string $priority
    ): AssetRepairRequest {
        return AssetRepairRequest::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'requested_by_user_id' => $user->id,
            'status' => $status,
            'priority' => $priority,
            'title' => $title,
            'problem_description' => 'Repair V5 filter fixture',
            'reported_at' => now(),
            'completed_at' => $status === AssetRepairRequest::STATUS_COMPLETED ? now() : null,
        ]);
    }
}