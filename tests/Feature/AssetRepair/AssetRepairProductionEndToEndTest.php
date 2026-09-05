<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\AssetRepairWorkOrder;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowRuntimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AssetRepairProductionEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    public function test_external_repair_runs_through_real_http_lifecycle_with_audit_and_reporting(): void
    {
        [$company, $user, $employee, $asset] = $this->fixture('EXTERNAL');
        $this->grant($user, 'asset_repairs.view', 'asset_repairs.create', 'approvals.view', 'approvals.act', 'reports.view');
        $this->loginAs($user);

        $this->post(route('asset-repairs.store'), [
            'asset_id' => $asset->id,
            'title' => 'V6.8 External E2E',
            'problem_description' => 'Power system failure',
            'priority' => 'critical',
            'estimated_cost' => 900,
        ])->assertRedirect();

        $repair = AssetRepairRequest::withoutGlobalScopes()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->post(route('asset-repairs.submit', $repair))->assertRedirect();
        app(WorkflowRuntimeService::class)->approve($repair->fresh()->workflowInstance, $employee, $user, 'Production E2E approval');
        $repair->refresh();
        $this->assertSame(AssetRepairRequest::STATUS_APPROVED, $repair->status);

        $this->post(route('asset-repairs.start', $repair), [
            'repair_type' => 'external',
            'external_provider_name' => 'Production Service Center',
            'expected_return_at' => now()->addDays(2)->toDateString(),
        ])->assertForbidden();

        $this->grant($user, 'asset_repairs.manage');
        $this->post(route('asset-repairs.start', $repair), [
            'repair_type' => 'external',
            'external_provider_name' => 'Production Service Center',
            'expected_return_at' => now()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $this->post(route('asset-repairs.complete', $repair), [
            'diagnosis' => 'Failed power module',
            'repair_notes' => 'Module replaced and load tested',
            'outcome' => 'repaired',
            'labor_cost' => 100,
            'parts_cost' => 300,
            'external_service_cost' => 600,
        ])->assertRedirect();

        $repair->refresh();
        $this->assertSame('completed', $repair->status);
        $this->assertSame('1000.00', $repair->actual_cost);
        $this->assertSame('completed', $repair->workOrder->status);
        $this->assertSame('Production Service Center', $repair->workOrder->external_provider_name);
        $this->assertSame(['asset_repair.created', 'asset_repair.submitted', 'asset_repair.started', 'asset_repair.costs_updated', 'asset_repair.completed'], AuditLog::withoutGlobalScopes()->where('subject_type', AssetRepairRequest::class)->where('subject_id', $repair->id)->orderBy('id')->pluck('action')->all());

        $this->get(route('reports.repairs', ['provider' => 'Production Service']))
            ->assertOk()->assertSee('V6.8 External E2E')->assertSee('1,000.00');
    }

    public function test_invalid_http_completion_preserves_in_progress_work_order_and_costs(): void
    {
        [$company, $user, $employee, $asset] = $this->fixture('ROLLBACK');
        $this->grant($user, 'asset_repairs.view', 'asset_repairs.create', 'asset_repairs.manage');
        $this->loginAs($user);

        $repair = $this->approvedRepairViaHttp($company, $user, $employee, $asset);
        $this->post(route('asset-repairs.start', $repair), ['repair_type' => 'internal'])->assertRedirect();
        $this->post(route('asset-repairs.complete', $repair), [
            'diagnosis' => '', 'repair_notes' => '', 'outcome' => 'invalid',
            'labor_cost' => 500, 'parts_cost' => 500, 'external_service_cost' => 500,
        ])->assertSessionHasErrors(['diagnosis', 'repair_notes', 'outcome']);

        $repair->refresh();
        $workOrder = AssetRepairWorkOrder::withoutGlobalScopes()->where('asset_repair_request_id', $repair->id)->firstOrFail();
        $this->assertSame('in_repair', $repair->status);
        $this->assertSame('in_progress', $workOrder->status);
        $this->assertSame('0.00', $workOrder->total_cost);
    }

    private function approvedRepairViaHttp(Company $company, User $user, Employee $employee, Asset $asset): AssetRepairRequest
    {
        $this->post(route('asset-repairs.store'), ['asset_id' => $asset->id, 'title' => 'V6.8 Rollback', 'problem_description' => 'Failure', 'priority' => 'normal'])->assertRedirect();
        $repair = AssetRepairRequest::withoutGlobalScopes()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->post(route('asset-repairs.submit', $repair))->assertRedirect();
        app(WorkflowRuntimeService::class)->approve($repair->fresh()->workflowInstance, $employee, $user);

        return $repair->fresh();
    }

    private function fixture(string $suffix): array
    {
        $company = Company::withoutGlobalScopes()->create(['name' => 'V68 '.$suffix, 'code' => 'V68-'.$suffix.'-'.substr(uniqid(), -5), 'is_active' => true]);
        $role = Role::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'V68-'.$suffix, 'display_name' => 'V68', 'is_active' => true]);
        $password = 'V68!password';
        $user = User::withoutGlobalScopes()->create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => 'V68 User', 'username' => 'v68_'.strtolower($suffix).substr(uniqid(), -5), 'email' => strtolower($suffix).substr(uniqid(), -5).'@v68.test', 'password' => Hash::make($password), 'is_active' => true]);
        $user->setAttribute('plain_test_password', $password);
        $employee = Employee::withoutGlobalScopes()->create(['company_id' => $company->id, 'user_id' => $user->id, 'personnel_code' => 'V68E-'.substr(uniqid(), -6), 'display_name' => 'V68 Employee', 'is_active' => true]);
        $category = AssetCategory::query()->firstOrCreate(['name' => 'V68 Category'], ['code' => 'V68-CAT-'.substr(uniqid(), -5), 'is_active' => true]);
        $asset = Asset::withoutGlobalScopes()->create(['company_id' => $company->id, 'asset_category_id' => $category->id, 'inventory_code' => 'V68-A-'.substr(uniqid(), -6), 'title' => 'V68 Asset', 'status' => 'warehouse', 'is_active' => true]);
        $workflow = Workflow::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'V68 Repair', 'code' => 'V68-W-'.substr(uniqid(), -6), 'process_type' => 'asset_repair', 'is_active' => true, 'is_default' => true, 'version' => 1]);
        WorkflowStep::query()->create(['workflow_id' => $workflow->id, 'name' => 'Approval', 'code' => 'V68-APPROVE', 'step_type' => 'approval', 'approver_type' => 'requester', 'sort_order' => 10, 'is_required' => true, 'is_active' => true, 'rejection_action' => 'terminate']);

        return [$company, $user, $employee, $asset];
    }

    private function grant(User $user, string ...$names): void
    {
        $permissions = Permission::query()->whereIn('name', $names)->get();
        $role = Role::withoutGlobalScopes()->findOrFail($user->role_id);
        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $user->unsetRelation('role');
    }

    private function loginAs(User $user): void
    {
        $this->post('/login', ['username' => $user->username, 'password' => $user->getAttribute('plain_test_password')])->assertRedirect(route('dashboard'));
    }
}
