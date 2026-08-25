<?php

declare(strict_types=1);

namespace Tests\Feature\AssetCode;

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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssetCodeFinalDeliveryEndToEndTest extends TestCase
{
    use DatabaseTransactions;


    public function test_real_issuance_plate_and_final_delivery_complete_the_asset_lifecycle(): void
    {
        $actor =
            $this->actorUser();


        $employee =
            $this->recipientEmployee(
                $actor
            );


        [
            $asset,
            $site,
        ] =
            $this->newWarehouseAssetForOfficialIssuance();


        $issued =
            app(
                AssetCodeIssuanceService::class
            )
                ->issue(
                    asset:
                        $asset,

                    codingSiteId:
                        (int) $site->id,

                    actor:
                        $actor
                );


        self::assertSame(
            '91-92-093-0001',
            $issued->asset_code
        );


        self::assertNotNull(
            $issued->asset_code_issued_at
        );


        self::assertSame(
            (int) $actor->id,
            (int) $issued->asset_code_issued_by_user_id
        );

        self::assertSame(
            $issued->asset_code,
            $issued->plate_number
        );


        [
            $inventoryRequest,
            $item,
            $allocation,
            $instance,
            $step,
        ] =
            $this->deliveryFixture(
                asset:
                    $issued,

                actor:
                    $actor,

                employee:
                    $employee
            );


        app(
            FinalWarehouseDeliveryService::class
        )
            ->deliver(
                step:
                    $step,

                actorUser:
                    $actor,

                httpRequest:
                    Request::create(
                        '/test/final-delivery',
                        'POST'
                    )
            );


        $issued->refresh();
        $allocation->refresh();
        $inventoryRequest->refresh();
        $item->refresh();


        self::assertSame(
            'assigned',
            $issued->status
        );


        self::assertSame(
            'employee',
            $issued->custody_type
        );


        self::assertSame(
            (int) $actor->id,
            (int) $issued->custody_user_id
        );


        self::assertSame(
            (int) $employee->id,
            (int) $issued->custody_employee_id
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


        $this->assertDatabaseHas(
            'asset_transactions',
            [
                'company_id' =>
                    2,

                'asset_id' =>
                    $issued->id,

                'to_user_id' =>
                    $actor->id,

                'type' =>
                    'delivery',

                'plate_number' =>
                    $issued->asset_code,

                'created_by' =>
                    $actor->id,

                'from_custody_type' =>
                    'warehouse',

                'to_custody_type' =>
                    'employee',

                'to_employee_id' =>
                    $employee->id,
            ]
        );


        $this->assertDatabaseHas(
            'asset_code_sequences',
            [
                'company_id' =>
                    2,

                'prefix' =>
                    '91-92-093',

                'last_sequence' =>
                    1,
            ]
        );


        self::assertSame(
            (int) $step->id,
            (int) $instance->current_step_id
        );
    }


    public function test_final_delivery_rejects_asset_without_permanent_code_before_any_write(): void
    {
        $actor =
            $this->actorUser();


        $employee =
            $this->recipientEmployee(
                $actor
            );


        $asset =
            $this->plainWarehouseAsset(
                assetCode:
                    null,

                plateNumber:
                    'PL-E2E-CODE-GUARD'
            );


        [
            $inventoryRequest,
            $item,
            $allocation,
            $instance,
            $step,
        ] =
            $this->deliveryFixture(
                asset:
                    $asset,

                actor:
                    $actor,

                employee:
                    $employee
            );


        try {

            app(
                FinalWarehouseDeliveryService::class
            )
                ->deliver(
                    step:
                        $step,

                    actorUser:
                        $actor,

                    httpRequest:
                        Request::create(
                            '/test/final-delivery',
                            'POST'
                        )
                );


            self::fail(
                'Delivery without permanent asset code should have been rejected.'
            );

        } catch (ValidationException $exception) {

            self::assertArrayHasKey(
                'asset',
                $exception->errors()
            );
        }


        $asset->refresh();
        $allocation->refresh();
        $inventoryRequest->refresh();
        $item->refresh();


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
            DB::table(
                'asset_transactions'
            )
                ->where(
                    'asset_id',
                    $asset->id
                )
                ->count()
        );
    }

    public function test_final_delivery_uses_asset_code_as_physical_plate_identifier(): void
    {
        $actor =
            $this->actorUser();

        $employee =
            $this->recipientEmployee(
                $actor
            );

        $asset =
            $this->plainWarehouseAsset(
                assetCode:
                    '91-92-093-0099',

                plateNumber:
                    null
            );

        [
            $inventoryRequest,
            $item,
            $allocation,
            $instance,
            $step,
        ] =
            $this->deliveryFixture(
                asset:
                    $asset,

                actor:
                    $actor,

                employee:
                    $employee
            );

        self::assertSame(
            $asset->asset_code,
            $asset->plate_number
        );

        app(
            FinalWarehouseDeliveryService::class
        )
            ->deliver(
                step:
                    $step,

                actorUser:
                    $actor,

                httpRequest:
                    Request::create(
                        '/test/final-delivery',
                        'POST'
                    )
            );

        $asset->refresh();
        $allocation->refresh();
        $inventoryRequest->refresh();
        $item->refresh();

        self::assertSame(
            'assigned',
            $asset->status
        );

        self::assertSame(
            $asset->asset_code,
            $asset->plate_number
        );

        self::assertSame(
            'delivered',
            $allocation->status
        );

        self::assertSame(
            'fulfilled',
            $item->status
        );

        self::assertSame(
            'fulfilled',
            $inventoryRequest->status
        );

        $this->assertDatabaseHas(
            'asset_transactions',
            [
                'company_id' =>
                    2,

                'asset_id' =>
                    $asset->id,

                'type' =>
                    'delivery',

                'plate_number' =>
                    $asset->asset_code,
            ]
        );
    }



    private function newWarehouseAssetForOfficialIssuance(): array
    {
        AssetCodeFormulaSetting::query()
            ->updateOrCreate(
                [
                    'company_id' =>
                        2,
                ],
                [
                    'segment_order' => [
                        'site',
                        'category',
                        'type',
                        'serial',
                    ],

                    'separator' =>
                        '-',

                    'site_length' =>
                        2,

                    'category_length' =>
                        2,

                    'type_length' =>
                        3,

                    'serial_length' =>
                        4,

                    'sequence_scope' =>
                        'family',

                    'enforce_segment_lengths' =>
                        true,
                ]
            );


        $site =
            Site::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'name' =>
                        'E2E Final Delivery Coding Site',

                    'code' =>
                        '91',

                    'type' =>
                        'factory',

                    'is_active' =>
                        true,

                    'sort_order' =>
                        991,
                ]);


        AssetCategoryCodingMapping::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' =>
                        2,

                    'asset_category_id' =>
                        1,
                ],
                [
                    'coding_code' =>
                        '92',

                    'is_active' =>
                        true,
                ]
            );


        $type =
            AssetType::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'asset_category_id' =>
                        1,

                    'name' =>
                        'E2E Final Delivery Type',

                    'code' =>
                        'E2E-FINAL-DELIVERY',

                    'coding_code' =>
                        '093',

                    'description' =>
                        'End-to-end delivery test asset type.',

                    'is_active' =>
                        true,

                    'sort_order' =>
                        991,
                ]);


        AssetCodeSequence::query()
            ->where(
                'company_id',
                2
            )
            ->where(
                'prefix',
                '91-92-093'
            )
            ->delete();


        $asset =
            Asset::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'asset_category_id' =>
                        1,

                    'asset_type_id' =>
                        $type->id,

                    'asset_code' =>
                        null,

                    'title' =>
                        'E2E Official Issuance Asset',

                    'purchase_price' =>
                        0,

                    'status' =>
                        'warehouse',

                    'plate_number' =>
                        null,

                    'custody_type' =>
                        'warehouse',

                    'is_active' =>
                        true,
                ]);


        return [
            $asset,
            $site,
        ];
    }


    private function plainWarehouseAsset(
        ?string $assetCode,
        ?string $plateNumber
    ): Asset {

        $type =
            AssetType::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'asset_category_id' =>
                        1,

                    'name' =>
                        'E2E Guard Type '
                        .
                        bin2hex(
                            random_bytes(
                                4
                            )
                        ),

                    'code' =>
                        'E2EGUARD'
                        .
                        strtoupper(
                            bin2hex(
                                random_bytes(
                                    4
                                )
                            )
                        ),

                    'coding_code' =>
                        '094',

                    'description' =>
                        'Delivery guard test asset type.',

                    'is_active' =>
                        true,

                    'sort_order' =>
                        992,
                ]);


        return Asset::withoutGlobalScopes()
            ->create([
                'company_id' =>
                    2,

                'asset_category_id' =>
                    1,

                'asset_type_id' =>
                    $type->id,

                'asset_code' =>
                    $assetCode,

                'title' =>
                    'E2E Delivery Guard Asset',

                'purchase_price' =>
                    0,

                'status' =>
                    'warehouse',

                'plate_number' =>
                    $plateNumber,

                'custody_type' =>
                    'warehouse',

                'is_active' =>
                    true,
            ]);
    }


    private function deliveryFixture(
        Asset $asset,
        User $actor,
        Employee $employee
    ): array {

        $requestNumber =
            'E2E-'
            .
            strtoupper(
                bin2hex(
                    random_bytes(
                        6
                    )
                )
            );


        $inventoryRequest =
            InventoryRequest::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'request_number' =>
                        $requestNumber,

                    'requester_employee_id' =>
                        $employee->id,

                    'requester_user_id' =>
                        $actor->id,

                    'site_id' =>
                        $employee->site_id,

                    'department_id' =>
                        $employee->department_id,

                    'workflow_instance_id' =>
                        null,

                    'status' =>
                        'approved',

                    'priority' =>
                        'normal',

                    'purpose' =>
                        'Asset code final delivery E2E test',

                    'submitted_at' =>
                        now(),

                    'approved_at' =>
                        now(),
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

                    'item_code' =>
                        'E2E-ITEM',

                    'item_name' =>
                        'E2E Final Delivery Item',

                    'unit' =>
                        'عدد',

                    'requested_quantity' =>
                        1,

                    'approved_quantity' =>
                        1,

                    'fulfilled_quantity' =>
                        0,

                    'status' =>
                        'approved',

                    'sort_order' =>
                        1,
                ]);


        $instance =
            WorkflowInstance::withoutGlobalScopes()
                ->create([
                    'company_id' =>
                        2,

                    'workflow_id' =>
                        null,

                    'workflow_name' =>
                        'E2E Final Delivery Workflow',

                    'workflow_code' =>
                        'E2E-FINAL-DELIVERY',

                    'process_type' =>
                        'inventory_request',

                    'workflow_version' =>
                        1,

                    'subject_type' =>
                        InventoryRequest::class,

                    'subject_id' =>
                        $inventoryRequest->id,

                    'requester_employee_id' =>
                        $employee->id,

                    'requester_user_id' =>
                        $actor->id,

                    'status' =>
                        'pending',

                    'current_step_id' =>
                        null,

                    'started_at' =>
                        now(),
                ]);


        $step =
            WorkflowInstanceStep::query()
                ->create([
                    'workflow_instance_id' =>
                        $instance->id,

                    'workflow_step_id' =>
                        null,

                    'name' =>
                        'تحویل نهایی انبار',

                    'code' =>
                        'FINAL-WAREHOUSE-DELIVERY',

                    'step_type' =>
                        'action',

                    'approver_type' =>
                        'user',

                    'approver_reference_id' =>
                        $actor->id,

                    'sort_order' =>
                        100,

                    'is_required' =>
                        true,

                    'rejection_action' =>
                        'terminate',

                    'resolved_employee_id' =>
                        $employee->id,

                    'resolved_user_id' =>
                        $actor->id,

                    'status' =>
                        'pending',

                    'activated_at' =>
                        now(),
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
                    'company_id' =>
                        2,

                    'inventory_request_id' =>
                        $inventoryRequest->id,

                    'inventory_request_item_id' =>
                        $item->id,

                    'asset_id' =>
                        $asset->id,

                    'status' =>
                        'approved',

                    'reserved_by_user_id' =>
                        $actor->id,

                    'reserved_by_employee_id' =>
                        $employee->id,

                    'reserved_at' =>
                        now(),

                    'approved_at' =>
                        now(),

                    'note' =>
                        'E2E approved allocation',
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
                ->whereKey(
                    6
                )
                ->where(
                    'company_id',
                    2
                )
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
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->first();


        if ($existing !== null) {

            if (!$existing->is_active) {

                DB::table(
                    'employees'
                )
                    ->where(
                        'id',
                        $existing->id
                    )
                    ->update([
                        'is_active' =>
                            true,

                        'updated_at' =>
                            now(),
                    ]);

                $existing->refresh();
            }


            return $existing;
        }


        $employeeId =
            DB::table(
                'employees'
            )
                ->insertGetId([
                    'company_id' =>
                        2,

                    'user_id' =>
                        $user->id,

                    'department_id' =>
                        null,

                    'site_id' =>
                        null,

                    'manager_employee_id' =>
                        null,

                    'personnel_code' =>
                        'E2E-EMP-'
                        .
                        $user->id,

                    'first_name' =>
                        'E2E',

                    'last_name' =>
                        'Recipient',

                    'display_name' =>
                        'E2E Recipient',

                    'job_title' =>
                        'E2E Test Recipient',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);


        return Employee::withoutGlobalScopes()
            ->findOrFail(
                $employeeId
            );
    }
}