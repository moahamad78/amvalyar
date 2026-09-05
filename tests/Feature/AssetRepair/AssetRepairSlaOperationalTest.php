<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\AssetRepairWorkOrder;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OperationalAlertService;
use App\Services\TaskCenterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AssetRepairSlaOperationalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_repair_sla_alerts_are_prioritized_and_tenant_scoped(): void
    {
        [$company, $manager, $asset] = $this->fixture('ALERT-A');
        [$otherCompany, $otherManager, $otherAsset] = $this->fixture('ALERT-B');

        $overdue = $this->repair($company, $manager, $asset, 'OVERDUE REPAIR', 'normal', now()->subHour());
        $dueSoon = $this->repair($company, $manager, $asset, 'DUE SOON REPAIR', 'normal', now()->addHours(12));
        $critical = $this->repair($company, $manager, $asset, 'CRITICAL REPAIR', 'critical');
        $foreign = $this->repair($otherCompany, $otherManager, $otherAsset, 'FOREIGN OVERDUE', 'normal', now()->subDay());

        $alerts = app(OperationalAlertService::class)->alertsFor($manager);
        $keys = $alerts->pluck('key');

        $this->assertTrue($keys->contains('repair-overdue-'.$overdue->id));
        $this->assertTrue($keys->contains('repair-due-soon-'.$dueSoon->id));
        $this->assertTrue($keys->contains('repair-critical-'.$critical->id));
        $this->assertFalse($keys->contains('repair-overdue-'.$foreign->id));
        $this->assertSame('critical', $alerts->firstWhere('key', 'repair-overdue-'.$overdue->id)['severity']);
        $this->assertSame('warning', $alerts->firstWhere('key', 'repair-due-soon-'.$dueSoon->id)['severity']);
    }

    public function test_repair_manager_receives_actionable_sla_tasks(): void
    {
        [$company, $manager, $asset] = $this->fixture('TASKS');

        $overdue = $this->repair($company, $manager, $asset, 'OVERDUE TASK', 'normal', now()->subHour());
        $approved = $this->repair($company, $manager, $asset, 'APPROVED CRITICAL', 'critical', null, AssetRepairRequest::STATUS_APPROVED);

        $tasks = app(TaskCenterService::class)->tasksFor($manager)
            ->where('workspace', 'repair')
            ->values();

        $this->assertSame([$overdue->id, $approved->id], $tasks->pluck('task_id')->all());
        $this->assertTrue($tasks[0]['is_overdue']);
        $this->assertTrue($tasks[1]['is_critical']);
        $this->assertSame('asset-repairs.show', $tasks[0]['route']);
    }

    public function test_repair_workspace_filters_overdue_sla_without_cross_tenant_leakage(): void
    {
        [$company, $manager, $asset] = $this->fixture('FILTER-A');
        [$otherCompany, $otherManager, $otherAsset] = $this->fixture('FILTER-B');

        $this->repair($company, $manager, $asset, 'VISIBLE OVERDUE SLA', 'normal', now()->subHour());
        $this->repair($company, $manager, $asset, 'DUE SOON SLA', 'normal', now()->addHours(12));
        $this->repair($company, $manager, $asset, 'CRITICAL SLA', 'critical', null, AssetRepairRequest::STATUS_APPROVED);
        $this->repair($otherCompany, $otherManager, $otherAsset, 'FOREIGN OVERDUE SLA', 'normal', now()->subHour());

        $this->loginAs($manager);

        $this->get(route('asset-repairs.index', ['sla' => 'overdue']))
            ->assertOk()
            ->assertSee('VISIBLE OVERDUE SLA')
            ->assertDontSee('DUE SOON SLA')
            ->assertDontSee('CRITICAL SLA')
            ->assertDontSee('FOREIGN OVERDUE SLA')
            ->assertSee('سررسید گذشته');

        $this->get(route('asset-repairs.index', ['sla' => 'due_soon']))
            ->assertOk()
            ->assertSee('DUE SOON SLA')
            ->assertDontSee('VISIBLE OVERDUE SLA')
            ->assertDontSee('CRITICAL SLA');

        $this->get(route('asset-repairs.index', ['sla' => 'critical']))
            ->assertOk()
            ->assertSee('CRITICAL SLA')
            ->assertDontSee('VISIBLE OVERDUE SLA')
            ->assertDontSee('DUE SOON SLA');
    }

    public function test_repair_report_aggregates_filtered_company_costs_without_tenant_leakage(): void
    {
        [$company, $manager, $asset] = $this->fixture('REPORT-A');
        [$otherCompany, $otherManager, $otherAsset] = $this->fixture('REPORT-B');

        $visible = $this->repair($company, $manager, $asset, 'VISIBLE REPORT REPAIR', 'normal', now());
        $visible->update(['status' => AssetRepairRequest::STATUS_COMPLETED, 'estimated_cost' => 800, 'actual_cost' => 1000, 'completed_at' => now()]);
        $visible->workOrder()->withoutGlobalScopes()->update(['status' => AssetRepairWorkOrder::STATUS_COMPLETED, 'outcome' => 'repaired', 'external_provider_name' => 'Trusted Provider']);

        $foreign = $this->repair($otherCompany, $otherManager, $otherAsset, 'FOREIGN REPORT REPAIR', 'normal', now());
        $foreign->update(['status' => AssetRepairRequest::STATUS_COMPLETED, 'actual_cost' => 9000, 'completed_at' => now()]);

        $this->loginAs($manager);
        $this->get(route('reports.repairs', ['provider' => 'Trusted']))
            ->assertOk()
            ->assertSee('VISIBLE REPORT REPAIR')
            ->assertSee('Trusted Provider')
            ->assertSee('1,000.00')
            ->assertSee('200.00')
            ->assertDontSee('FOREIGN REPORT REPAIR')
            ->assertDontSee('9,000.00');
    }

    private function fixture(string $suffix): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Repair SLA Company '.$suffix,
            'code' => 'RSLA-'.$suffix.'-'.strtoupper(substr(uniqid(), -5)),
            'is_active' => true,
        ]);

        $role = Role::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'asset_manager',
            'display_name' => 'Repair SLA Manager',
            'is_active' => true,
            'is_system' => false,
        ]);

        $password = 'RepairSla!123';
        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => 'Repair SLA Manager '.$suffix,
            'username' => 'repair_sla_'.strtolower($suffix).'_'.strtolower(substr(uniqid(), -5)),
            'email' => strtolower($suffix).'.'.substr(uniqid(), -5).'@sla.test',
            'password' => Hash::make($password),
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        $user->setAttribute('plain_test_password', $password);

        $permissions = Permission::query()
            ->whereIn('name', ['asset_repairs.view', 'asset_repairs.manage', 'reports.view'])
            ->get();
        self::assertCount(3, $permissions);
        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $user->unsetRelation('role');

        $category = AssetCategory::query()->firstOrCreate(
            ['name' => 'Repair SLA Category'],
            ['code' => 'RSLA-CAT-'.strtoupper(substr(uniqid(), -5)), 'is_active' => true]
        );
        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'RSLA-'.$suffix.'-'.strtoupper(substr(uniqid(), -5)),
            'title' => 'Repair SLA Asset '.$suffix,
            'status' => 'warehouse',
            'is_active' => true,
        ]);

        return [$company, $user, $asset];
    }

    private function repair(
        Company $company,
        User $user,
        Asset $asset,
        string $title,
        string $priority,
        mixed $expectedReturnAt = null,
        string $status = AssetRepairRequest::STATUS_IN_REPAIR
    ): AssetRepairRequest {
        $repair = AssetRepairRequest::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'requested_by_user_id' => $user->id,
            'status' => $status,
            'priority' => $priority,
            'title' => $title,
            'problem_description' => 'SLA operational fixture',
            'reported_at' => now()->subDays(2),
            'started_at' => $status === AssetRepairRequest::STATUS_IN_REPAIR ? now()->subDay() : null,
        ]);

        if ($status === AssetRepairRequest::STATUS_IN_REPAIR) {
            AssetRepairWorkOrder::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'asset_repair_request_id' => $repair->id,
                'work_order_number' => 'SLA-'.$company->id.'-'.strtoupper(substr(uniqid(), -8)),
                'repair_type' => AssetRepairWorkOrder::TYPE_INTERNAL,
                'status' => AssetRepairWorkOrder::STATUS_IN_PROGRESS,
                'received_at' => now()->subDay(),
                'expected_return_at' => $expectedReturnAt,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);
        }

        return $repair;
    }

    private function loginAs(User $user): void
    {
        $this->post('/login', [
            'username' => $user->username,
            'password' => $user->getAttribute('plain_test_password'),
        ])->assertRedirect(route('dashboard'));
    }
}
