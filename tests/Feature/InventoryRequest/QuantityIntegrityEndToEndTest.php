<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Models\Asset;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCodeFormulaSetting;
use App\Models\AssetCodeSequence;
use App\Models\AssetType;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\InventoryRequestItem;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\AssetCode\AssetCodeIssuanceService;
use App\Services\FinalWarehouseDeliveryService;
use App\Services\InventoryAssetAllocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuantityIntegrityEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    public function test_quantity_ten_requires_ten_independent_assets_codes_plates_allocations_and_deliveries(): void
    {
        $actor = $this->actorUser();
        $employee = $this->recipientEmployee($actor);

        [$site, $type] = $this->codingFoundation();

        $inventoryRequest = InventoryRequest::withoutGlobalScopes()->create([
            'company_id' => 2,
            'request_number' => 'QTY10-' . strtoupper(bin2hex(random_bytes(6))),
            'requester_employee_id' => $employee->id,
            'requester_user_id' => $actor->id,
            'site_id' => $employee->site_id,
            'department_id' => $employee->department_id,
            'delivery_target_type' => 'employee',
            'workflow_instance_id' => null,
            'status' => 'approved',
            'priority' => 'normal',
            'purpose' => 'Quantity integrity E2E',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $item = InventoryRequestItem::query()->create([
            'inventory_request_id' => $inventoryRequest->id,
            'asset_category_id' => 1,
            'asset_type_id' => $type->id,
            'item_code' => 'QTY10-ITEM',
            'item_name' => 'Quantity Ten Plate Eligible Asset',
            'unit' => 'عدد',
            'requested_quantity' => 10,
            'approved_quantity' => 10,
            'fulfilled_quantity' => 0,
            'status' => 'approved',
            'sort_order' => 1,
        ]);

        [$instance, $step] = $this->finalDeliveryStep(
            inventoryRequest: $inventoryRequest,
            actor: $actor,
            employee: $employee,
        );

        $issuance = app(AssetCodeIssuanceService::class);
        $allocationService = app(InventoryAssetAllocationService::class);

        $assets = collect();
        $allocations = collect();

        for ($i = 1; $i <= 10; $i++) {
            $asset = Asset::withoutGlobalScopes()->create([
                'company_id' => 2,
                'asset_category_id' => 1,
                'asset_type_id' => $type->id,
                'asset_code' => null,
                'title' => 'Quantity Integrity Asset ' . $i,
                'purchase_price' => 0,
                'status' => 'warehouse',
                'plate_number' => null,
                'custody_type' => 'warehouse',
                'is_active' => true,
            ]);

            $asset = $issuance->issue(
                asset: $asset,
                codingSiteId: (int) $site->id,
                actor: $actor,
            );

            $allocation = $allocationService->reserve(
                request: $inventoryRequest->fresh(),
                item: $item->fresh(),
                asset: $asset->fresh(),
                actor: $actor,
            );

            $allocation->update([
                'status' => 'approved',
                'approved_at' => now(),
                'note' => 'Quantity integrity E2E approved allocation',
            ]);

            $assets->push($asset->fresh());
            $allocations->push($allocation->fresh());
        }

        self::assertCount(10, $assets);
        self::assertCount(10, $allocations);
        self::assertCount(10, $assets->pluck('id')->unique());
        self::assertCount(10, $assets->pluck('asset_code')->unique());

        foreach ($assets as $asset) {
            self::assertNotNull($asset->asset_code);
            self::assertSame($asset->asset_code, $asset->plate_number);
        }

        self::assertSame(
            10,
            InventoryRequestAllocation::query()
                ->where('inventory_request_item_id', $item->id)
                ->where('status', 'approved')
                ->count()
        );

        $eleventh = Asset::withoutGlobalScopes()->create([
            'company_id' => 2,
            'asset_category_id' => 1,
            'asset_type_id' => $type->id,
            'asset_code' => null,
            'title' => 'Quantity Integrity Overflow Asset',
            'purchase_price' => 0,
            'status' => 'warehouse',
            'plate_number' => null,
            'custody_type' => 'warehouse',
            'is_active' => true,
        ]);

        try {
            $allocationService->reserve(
                request: $inventoryRequest->fresh(),
                item: $item->fresh(),
                asset: $eleventh,
                actor: $actor,
            );

            self::fail('An eleventh allocation must be rejected for approved quantity ten.');
        }
        catch (ValidationException $exception) {
            self::assertArrayHasKey('asset', $exception->errors());
        }

        app(FinalWarehouseDeliveryService::class)->deliver(
            step: $step,
            actorUser: $actor,
            httpRequest: Request::create('/test/quantity-integrity-final-delivery', 'POST'),
        );

        $inventoryRequest->refresh();
        $item->refresh();

        self::assertSame('10.000', (string) $item->fulfilled_quantity);
        self::assertSame('fulfilled', $item->status);
        self::assertSame('fulfilled', $inventoryRequest->status);
        self::assertNotNull($inventoryRequest->fulfilled_at);

        $deliveredAllocations = InventoryRequestAllocation::query()
            ->where('inventory_request_item_id', $item->id)
            ->where('status', 'delivered')
            ->get();

        self::assertCount(10, $deliveredAllocations);

        foreach ($assets as $asset) {
            $asset->refresh();

            self::assertSame('assigned', $asset->status);
            self::assertSame('employee', $asset->custody_type);
            self::assertSame((int) $actor->id, (int) $asset->custody_user_id);
            self::assertSame((int) $employee->id, (int) $asset->custody_employee_id);
            self::assertSame($asset->asset_code, $asset->plate_number);
        }

        self::assertSame(
            10,
            DB::table('asset_transactions')
                ->whereIn('asset_id', $assets->pluck('id')->all())
                ->where('type', 'delivery')
                ->count()
        );

        $transactionPlates = DB::table('asset_transactions')
            ->whereIn('asset_id', $assets->pluck('id')->all())
            ->where('type', 'delivery')
            ->pluck('plate_number');

        self::assertCount(10, $transactionPlates);
        self::assertCount(10, $transactionPlates->unique());

        self::assertSame(
            10,
            (int) AssetCodeSequence::query()
                ->where('company_id', 2)
                ->where('prefix', '97-98-097')
                ->value('last_sequence')
        );

        self::assertSame((int) $step->id, (int) $instance->fresh()->current_step_id);
    }

    private function codingFoundation(): array
    {
        AssetCodeFormulaSetting::query()->updateOrCreate(
            ['company_id' => 2],
            [
                'segment_order' => ['site', 'category', 'type', 'serial'],
                'separator' => '-',
                'site_length' => 2,
                'category_length' => 2,
                'type_length' => 3,
                'serial_length' => 4,
                'sequence_scope' => 'family',
                'enforce_segment_lengths' => true,
            ]
        );

        $site = Site::withoutGlobalScopes()->create([
            'company_id' => 2,
            'name' => 'Quantity Integrity Coding Site',
            'code' => '97',
            'type' => 'factory',
            'is_active' => true,
            'sort_order' => 997,
        ]);

        AssetCategoryCodingMapping::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => 2,
                'asset_category_id' => 1,
            ],
            [
                'coding_code' => '98',
                'is_active' => true,
            ]
        );

        $type = AssetType::withoutGlobalScopes()->create([
            'company_id' => 2,
            'asset_category_id' => 1,
            'name' => 'Quantity Integrity Type ' . bin2hex(random_bytes(4)),
            'code' => 'QTY10' . strtoupper(bin2hex(random_bytes(4))),
            'coding_code' => '097',
            'description' => 'Quantity integrity E2E asset type.',
            'is_active' => true,
            'sort_order' => 997,
        ]);

        AssetCodeSequence::query()
            ->where('company_id', 2)
            ->where('prefix', '97-98-097')
            ->delete();

        return [$site, $type];
    }

    private function finalDeliveryStep(
        InventoryRequest $inventoryRequest,
        User $actor,
        Employee $employee
    ): array {
        $instance = InventoryRequest::withoutGlobalScopes()
            ->whereKey($inventoryRequest->id)
            ->lockForUpdate()
            ->firstOrFail();

        $workflowInstance = WorkflowInstance::withoutGlobalScopes()->create([
            'company_id' => 2,
            'workflow_id' => null,
            'workflow_name' => 'Quantity Integrity E2E Workflow',
            'workflow_code' => 'QTY-INTEGRITY-E2E',
            'process_type' => 'inventory_request',
            'workflow_version' => 1,
            'subject_type' => InventoryRequest::class,
            'subject_id' => $inventoryRequest->id,
            'requester_employee_id' => $employee->id,
            'requester_user_id' => $actor->id,
            'status' => 'pending',
            'current_step_id' => null,
            'started_at' => now(),
        ]);

        $step = WorkflowInstanceStep::query()->create([
            'workflow_instance_id' => $workflowInstance->id,
            'workflow_step_id' => null,
            'name' => 'تحویل نهایی انبار',
            'code' => 'FINAL-WAREHOUSE-DELIVERY',
            'step_type' => 'action',
            'approver_type' => 'user',
            'approver_reference_id' => $actor->id,
            'sort_order' => 100,
            'is_required' => true,
            'rejection_action' => 'terminate',
            'resolved_employee_id' => $employee->id,
            'resolved_user_id' => $actor->id,
            'status' => 'pending',
            'activated_at' => now(),
        ]);

        $workflowInstance->current_step_id = $step->id;
        $workflowInstance->save();

        $instance->workflow_instance_id = $workflowInstance->id;
        $instance->save();

        return [$workflowInstance->refresh(), $step];
    }

    private function actorUser(): User
    {
        $user = User::query()
            ->whereKey(6)
            ->where('company_id', 2)
            ->where('username', 'testadmin')
            ->where('is_active', true)
            ->first();

        self::assertNotNull($user, 'testadmin user was not found.');

        return $user;
    }

    private function recipientEmployee(User $user): Employee
    {
        $existing = Employee::withoutGlobalScopes()
            ->where('company_id', 2)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
            if (!$existing->is_active) {
                DB::table('employees')
                    ->where('id', $existing->id)
                    ->update([
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);

                $existing->refresh();
            }

            return $existing;
        }

        $employeeId = DB::table('employees')->insertGetId([
            'company_id' => 2,
            'user_id' => $user->id,
            'department_id' => null,
            'site_id' => null,
            'manager_employee_id' => null,
            'personnel_code' => 'QTY-E2E-EMP-' . $user->id,
            'first_name' => 'Quantity',
            'last_name' => 'Integrity',
            'display_name' => 'Quantity Integrity Recipient',
            'job_title' => 'Quantity Integrity Test Recipient',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Employee::withoutGlobalScopes()->findOrFail($employeeId);
    }
}