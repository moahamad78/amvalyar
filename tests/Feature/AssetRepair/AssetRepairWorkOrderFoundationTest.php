<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRepairRequest;
use App\Models\AssetRepairWorkOrder;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Services\AssetRepairWorkOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssetRepairWorkOrderFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_work_order_table_and_domain_contract_exist(): void
    {
        $this->assertTrue(Schema::hasTable('asset_repair_work_orders'));

        $this->assertTrue(Schema::hasColumns('asset_repair_work_orders', [
            'company_id',
            'asset_repair_request_id',
            'work_order_number',
            'repair_type',
            'assigned_employee_id',
            'external_provider_name',
            'status',
            'outcome',
            'received_at',
            'expected_return_at',
            'actual_return_at',
            'labor_cost',
            'parts_cost',
            'external_service_cost',
            'created_by_user_id',
            'updated_by_user_id',
        ]));

        $this->assertSame('internal', AssetRepairWorkOrder::TYPE_INTERNAL);
        $this->assertSame('external', AssetRepairWorkOrder::TYPE_EXTERNAL);
        $this->assertSame('unrepairable', AssetRepairWorkOrder::OUTCOME_UNREPAIRABLE);
    }

    public function test_approved_repair_can_receive_one_internal_work_order(): void
    {
        [$company, $user, $employee] = $this->actors('INT');
        $repair = $this->repair($company, $user, $employee, AssetRepairRequest::STATUS_APPROVED);

        $workOrder = app(AssetRepairWorkOrderService::class)->createForRepair(
            repairRequest: $repair,
            actor: $user,
            repairType: AssetRepairWorkOrder::TYPE_INTERNAL,
            assignedEmployee: $employee,
            notes: 'Internal bench repair'
        );

        $this->assertSame($company->id, $workOrder->company_id);
        $this->assertSame($repair->id, $workOrder->asset_repair_request_id);
        $this->assertSame($employee->id, $workOrder->assigned_employee_id);
        $this->assertSame(AssetRepairWorkOrder::STATUS_PLANNED, $workOrder->status);
        $this->assertStringStartsWith('RWO-' . $company->id . '-', $workOrder->work_order_number);
        $this->assertNull($workOrder->received_at);
        $this->assertSame('0.00', $workOrder->total_cost);
        $this->assertSame($workOrder->id, $repair->fresh()->workOrder->id);
    }

    public function test_in_progress_repair_creates_in_progress_work_order_with_received_time(): void
    {
        [$company, $user, $employee] = $this->actors('RUN');
        $repair = $this->repair($company, $user, $employee, AssetRepairRequest::STATUS_IN_REPAIR);

        $repair->update([
            'started_at' => now()->subMinutes(15),
        ]);

        $workOrder = app(AssetRepairWorkOrderService::class)->createForRepair(
            repairRequest: $repair->fresh(),
            actor: $user,
            repairType: AssetRepairWorkOrder::TYPE_INTERNAL,
            assignedEmployee: $employee
        );

        $this->assertSame(AssetRepairWorkOrder::STATUS_IN_PROGRESS, $workOrder->status);
        $this->assertNotNull($workOrder->received_at);
        $this->assertSame(
            $repair->fresh()->started_at->format('Y-m-d H:i:s'),
            $workOrder->received_at->format('Y-m-d H:i:s')
        );
    }

    public function test_external_work_order_requires_provider_and_total_cost_is_calculated(): void
    {
        [$company, $user, $employee] = $this->actors('EXT');
        $repair = $this->repair($company, $user, $employee, AssetRepairRequest::STATUS_APPROVED);

        $service = app(AssetRepairWorkOrderService::class);

        try {
            $service->createForRepair(
                repairRequest: $repair,
                actor: $user,
                repairType: AssetRepairWorkOrder::TYPE_EXTERNAL
            );

            $this->fail('Expected provider validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'external_provider_name',
                $exception->errors()
            );
        }

        $workOrder = $service->createForRepair(
            repairRequest: $repair,
            actor: $user,
            repairType: AssetRepairWorkOrder::TYPE_EXTERNAL,
            assignedEmployee: $employee,
            externalProviderName: 'Authorized Service Center'
        );

        $workOrder->update([
            'labor_cost' => 100000,
            'parts_cost' => 250000,
            'external_service_cost' => 500000,
        ]);

        $workOrder->refresh();

        $this->assertSame('Authorized Service Center', $workOrder->external_provider_name);
        $this->assertSame('850000.00', $workOrder->total_cost);
    }

    public function test_second_work_order_for_same_repair_is_rejected(): void
    {
        [$company, $user, $employee] = $this->actors('ONE');
        $repair = $this->repair($company, $user, $employee, AssetRepairRequest::STATUS_APPROVED);

        $service = app(AssetRepairWorkOrderService::class);

        $service->createForRepair(
            repairRequest: $repair,
            actor: $user,
            assignedEmployee: $employee
        );

        $this->expectException(ValidationException::class);

        $service->createForRepair(
            repairRequest: $repair,
            actor: $user,
            assignedEmployee: $employee
        );
    }

    public function test_cross_tenant_actor_and_draft_repair_are_rejected(): void
    {
        [$companyA, $userA, $employeeA] = $this->actors('A');
        [, $userB] = $this->actors('B');

        $approved = $this->repair(
            $companyA,
            $userA,
            $employeeA,
            AssetRepairRequest::STATUS_APPROVED
        );

        $service = app(AssetRepairWorkOrderService::class);

        try {
            $service->createForRepair(
                repairRequest: $approved,
                actor: $userB
            );

            $this->fail('Expected cross-tenant validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('actor', $exception->errors());
        }

        $draft = $this->repair(
            $companyA,
            $userA,
            $employeeA,
            AssetRepairRequest::STATUS_DRAFT,
            'DRAFT'
        );

        $this->expectException(ValidationException::class);

        $service->createForRepair(
            repairRequest: $draft,
            actor: $userA
        );
    }

    private function actors(string $suffix): array
    {
        $company = Company::withoutGlobalScopes()->create([
            'name' => 'Repair V6 Company ' . $suffix . ' ' . uniqid(),
            'code' => 'RV6-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'is_active' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Repair V6 User ' . $suffix,
            'username' => 'repair-v6-' . strtolower($suffix) . '-' . uniqid(),
            'email' => 'repair-v6-' . strtolower($suffix) . '-' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'personnel_code' => 'RV6E-' . $suffix . '-' . strtoupper(substr(uniqid(), -6)),
            'display_name' => 'Repair V6 Employee ' . $suffix,
            'is_active' => true,
        ]);

        return [$company, $user, $employee];
    }

    private function repair(
        Company $company,
        User $user,
        Employee $employee,
        string $status,
        string $suffix = 'BASE'
    ): AssetRepairRequest {
        $category = AssetCategory::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'RV6-INV-' . $suffix . '-' . uniqid(),
            'asset_code' => 'RV6-ASSET-' . $suffix . '-' . uniqid(),
            'title' => 'Repair V6 Asset ' . $suffix,
            'status' => 'warehouse',
            'is_active' => true,
        ]);

        return AssetRepairRequest::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'requested_by_user_id' => $user->id,
            'requested_by_employee_id' => $employee->id,
            'status' => $status,
            'priority' => AssetRepairRequest::PRIORITY_NORMAL,
            'title' => 'Repair V6 Request ' . $suffix,
            'problem_description' => 'Repair V6 foundation test',
            'reported_at' => now(),
        ]);
    }
}