<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\AssetType;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\InventoryRequestItem;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\FinalWarehouseDeliveryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class OrganizationalFinalDeliveryEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approved_organizational_request_is_delivered_to_organization_site(): void
    {
        $actor = $this->actorUser();
        $employee = $this->recipientEmployee($actor);

        $targetSite =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'name' =>
                        'E2E Organizational Target '
                        . bin2hex(random_bytes(4)),
                    'code' =>
                        'OT'
                        . strtoupper(
                            bin2hex(random_bytes(3))
                        ),
                    'type' => 'office',
                    'is_active' => true,
                    'sort_order' => 994,
                ]);

        $asset = $this->warehouseAsset();

        [
            $inventoryRequest,
            $item,
            $allocation,
            $instance,
            $step,
        ] = $this->deliveryFixture(
            asset: $asset,
            actor: $actor,
            employee: $employee,
            targetSite: $targetSite,
        );

        app(
            FinalWarehouseDeliveryService::class
        )->deliver(
            step: $step,
            actorUser: $actor,
            httpRequest: Request::create(
                '/test/organizational-final-delivery',
                'POST'
            )
        );

        $asset->refresh();
        $allocation->refresh();
        $item->refresh();
        $inventoryRequest->refresh();
        $instance->refresh();

        self::assertSame(
            'assigned',
            $asset->status
        );

        self::assertSame(
            'organization',
            $asset->custody_type
        );

        self::assertNull(
            $asset->custody_user_id
        );

        self::assertNull(
            $asset->custody_employee_id
        );

        self::assertSame(
            (int) $targetSite->id,
            (int) $asset->current_site_id
        );

        self::assertSame(
            'delivered',
            $allocation->status
        );

        self::assertNotNull(
            $allocation->delivered_at
        );

        self::assertSame(
            'fulfilled',
            $item->status
        );

        self::assertSame(
            '1.000',
            (string) $item->fulfilled_quantity
        );

        self::assertSame(
            'fulfilled',
            $inventoryRequest->status
        );

        self::assertNotNull(
            $inventoryRequest->fulfilled_at
        );

        self::assertSame(
            'organization',
            $inventoryRequest->delivery_target_type
        );

        self::assertSame(
            (int) $targetSite->id,
            (int) $inventoryRequest->target_site_id
        );

        $transaction =
            AssetTransaction::withoutGlobalScopes()
                ->where(
                    'asset_id',
                    $asset->id
                )
                ->where(
                    'type',
                    'delivery'
                )
                ->latest('id')
                ->first();

        self::assertNotNull(
            $transaction
        );

        self::assertSame(
            'warehouse',
            $transaction->from_custody_type
        );

        self::assertSame(
            'organization',
            $transaction->to_custody_type
        );

        self::assertNull(
            $transaction->to_user_id
        );

        self::assertNull(
            $transaction->to_employee_id
        );

        self::assertSame(
            (int) $targetSite->id,
            (int) $transaction->to_site_id
        );

        /*
         * In this system the permanent asset code and
         * the physical asset plate identifier are one value.
         */
        self::assertSame(
            $asset->asset_code,
            $transaction->plate_number
        );

        /*
         * Final delivery service does not advance the workflow itself;
         * the workflow action layer completes the pending step afterwards.
         * Therefore the current step must still be the final delivery step
         * at this service boundary.
         */
        self::assertSame(
            (int) $step->id,
            (int) $instance->current_step_id
        );
    }

    public function test_organizational_delivery_rejects_request_without_any_target_before_write(): void
    {
        $actor = $this->actorUser();
        $employee = $this->recipientEmployee($actor);
        $asset = $this->warehouseAsset();

        [
            $inventoryRequest,
            $item,
            $allocation,
            $instance,
            $step,
        ] = $this->deliveryFixture(
            asset: $asset,
            actor: $actor,
            employee: $employee,
            targetSite: null,
        );

        try {
            app(
                FinalWarehouseDeliveryService::class
            )->deliver(
                step: $step,
                actorUser: $actor,
                httpRequest: Request::create(
                    '/test/organizational-final-delivery',
                    'POST'
                )
            );

            self::fail(
                'Organizational final delivery without a destination should fail.'
            );
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'delivery_target',
                $exception->errors()
            );
        }

        $asset->refresh();
        $allocation->refresh();
        $item->refresh();
        $inventoryRequest->refresh();
        $instance->refresh();

        self::assertSame(
            'warehouse',
            $asset->status
        );

        self::assertSame(
            'warehouse',
            $asset->custody_type
        );

        self::assertSame(
            'approved',
            $allocation->status
        );

        self::assertNull(
            $allocation->delivered_at
        );

        self::assertNotSame(
            'fulfilled',
            $inventoryRequest->status
        );

        self::assertSame(
            0,
            AssetTransaction::withoutGlobalScopes()
                ->where(
                    'asset_id',
                    $asset->id
                )
                ->count()
        );

        self::assertSame(
            (int) $step->id,
            (int) $instance->current_step_id
        );
    }

    private function warehouseAsset(): Asset
    {
        $type =
            AssetType::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'name' =>
                        'Organizational E2E Type '
                        . bin2hex(random_bytes(4)),
                    'code' =>
                        'ORGE2E'
                        . strtoupper(
                            bin2hex(random_bytes(4))
                        ),
                    'coding_code' => '095',
                    'description' =>
                        'Organizational final delivery E2E type.',
                    'is_active' => true,
                    'sort_order' => 994,
                ]);

        $code =
            '95-95-095-'
            . strtoupper(
                bin2hex(random_bytes(3))
            );

        return
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'asset_category_id' => 1,
                    'asset_type_id' => $type->id,
                    'asset_code' => $code,
                    'plate_number' => $code,
                    'title' =>
                        'Organizational Final Delivery E2E Asset',
                    'purchase_price' => 0,
                    'status' => 'warehouse',
                    'custody_type' => 'warehouse',
                    'is_active' => true,
                ]);
    }

    private function deliveryFixture(
        Asset $asset,
        User $actor,
        Employee $employee,
        ?Site $targetSite
    ): array {
        $requestNumber =
            'ORG-E2E-'
            . strtoupper(
                bin2hex(random_bytes(6))
            );

        $inventoryRequest =
            InventoryRequest::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'request_number' => $requestNumber,
                    'requester_employee_id' => $employee->id,
                    'requester_user_id' => $actor->id,
                    'site_id' => $employee->site_id,
                    'department_id' => $employee->department_id,

                    'delivery_target_type' =>
                        'organization',

                    'target_site_id' =>
                        $targetSite?->id,

                    'target_department_id' =>
                        null,

                    'target_location_id' =>
                        null,

                    'workflow_instance_id' => null,
                    'status' => 'approved',
                    'priority' => 'normal',
                    'purpose' =>
                        'Organizational final delivery E2E test',
                    'submitted_at' => now(),
                    'approved_at' => now(),
                ]);

        $item =
            InventoryRequestItem::query()
                ->create([
                    'inventory_request_id' =>
                        $inventoryRequest->id,
                    'asset_category_id' =>
                        $asset->asset_category_id,
                    'asset_type_id' =>
                        $asset->asset_type_id,
                    'item_code' => 'ORG-E2E-ITEM',
                    'item_name' =>
                        'Organizational Final Delivery Item',
                    'unit' => 'عدد',
                    'requested_quantity' => 1,
                    'approved_quantity' => 1,
                    'fulfilled_quantity' => 0,
                    'status' => 'approved',
                    'sort_order' => 1,
                ]);

        $instance =
            WorkflowInstance::withoutGlobalScopes()
                ->create([
                    'company_id' => 2,
                    'workflow_id' => null,
                    'workflow_name' =>
                        'Organizational Final Delivery E2E Workflow',
                    'workflow_code' =>
                        'ORG-E2E-FINAL-DELIVERY',
                    'process_type' =>
                        'inventory_request',
                    'workflow_version' => 1,
                    'subject_type' =>
                        InventoryRequest::class,
                    'subject_id' =>
                        $inventoryRequest->id,
                    'requester_employee_id' =>
                        $employee->id,
                    'requester_user_id' =>
                        $actor->id,
                    'status' => 'pending',
                    'current_step_id' => null,
                    'started_at' => now(),
                ]);

        $step =
            WorkflowInstanceStep::query()
                ->create([
                    'workflow_instance_id' =>
                        $instance->id,
                    'workflow_step_id' => null,
                    'name' => 'تحویل نهایی انبار',
                    'code' =>
                        'FINAL-WAREHOUSE-DELIVERY',
                    'step_type' => 'action',
                    'approver_type' => 'user',
                    'approver_reference_id' =>
                        $actor->id,
                    'sort_order' => 100,
                    'is_required' => true,
                    'rejection_action' => 'terminate',
                    'resolved_employee_id' =>
                        $employee->id,
                    'resolved_user_id' =>
                        $actor->id,
                    'status' => 'pending',
                    'activated_at' => now(),
                ]);

        $instance->current_step_id =
            $step->id;
        $instance->save();

        $inventoryRequest->workflow_instance_id =
            $instance->id;
        $inventoryRequest->save();

        $allocation =
            InventoryRequestAllocation::query()
                ->create([
                    'company_id' => 2,
                    'inventory_request_id' =>
                        $inventoryRequest->id,
                    'inventory_request_item_id' =>
                        $item->id,
                    'asset_id' => $asset->id,
                    'status' => 'approved',
                    'reserved_by_user_id' =>
                        $actor->id,
                    'reserved_by_employee_id' =>
                        $employee->id,
                    'reserved_at' => now(),
                    'approved_at' => now(),
                    'note' =>
                        'Organizational E2E approved allocation',
                ]);

        return [
            $inventoryRequest,
            $item,
            $allocation,
            $instance->refresh(),
            $step,
        ];
    }

    private function actorUser(): User
    {
        $user =
            User::query()
                ->whereKey(6)
                ->where('company_id', 2)
                ->where(
                    'username',
                    'testadmin'
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        self::assertNotNull(
            $user,
            'testadmin user was not found.'
        );

        return $user;
    }

    private function recipientEmployee(
        User $user
    ): Employee {
        $existing =
            Employee::withoutGlobalScopes()
                ->where('company_id', 2)
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();

        if ($existing !== null) {
            if (!$existing->is_active) {
                DB::table('employees')
                    ->where(
                        'id',
                        $existing->id
                    )
                    ->update([
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);

                $existing->refresh();
            }

            return $existing;
        }

        $employeeId =
            DB::table('employees')
                ->insertGetId([
                    'company_id' => 2,
                    'user_id' => $user->id,
                    'department_id' => null,
                    'site_id' => null,
                    'manager_employee_id' => null,
                    'personnel_code' =>
                        'ORG-E2E-EMP-'
                        . $user->id,
                    'first_name' => 'E2E',
                    'last_name' => 'Recipient',
                    'display_name' =>
                        'E2E Recipient',
                    'job_title' =>
                        'E2E Test Recipient',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        return
            Employee::withoutGlobalScopes()
                ->findOrFail(
                    $employeeId
                );
    }
}