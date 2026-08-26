<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\DeliveryDispute;
use App\Models\DeliveryDisputeItem;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\InventoryRequestItem;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Modules\Core\Application\Security\Repositories\LoginSessionRepositoryInterface;
use Modules\Core\Domain\Security\Entities\LoginSession;
use Modules\Core\Domain\Security\ValueObjects\SessionId;
use Modules\Core\Domain\Security\ValueObjects\UserId;
use Tests\TestCase;

final class DeliveryDisputeFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_delivery_dispute_routes_exist(): void
    {
        self::assertTrue(
            Route::has(
                'delivery-disputes.index'
            )
        );

        self::assertTrue(
            Route::has(
                'delivery-disputes.show'
            )
        );

        self::assertTrue(
            Route::has(
                'delivery-disputes.store'
            )
        );
    }

    public function test_requester_can_report_delivered_asset_without_changing_physical_custody(): void
    {
        [$user, $employee] =
            $this->authenticateRequester();

        [
            $inventoryRequest,
            $allocation,
            $asset,
            $step,
            $instance,
        ] =
            $this->fixture(
                $user,
                $employee
            );

        $before = [
            'status' =>
                $asset->status,

            'custody_type' =>
                $asset->custody_type,

            'custody_user_id' =>
                $asset->custody_user_id,

            'custody_employee_id' =>
                $asset->custody_employee_id,

            'current_site_id' =>
                $asset->current_site_id,

            'current_location_id' =>
                $asset->current_location_id,
        ];

        $response =
            $this->post(
                route(
                    'delivery-disputes.store',
                    $step
                ),
                [
                    'reason_code' =>
                        'damaged',

                    'description' =>
                        'آسیب فیزیکی مشاهده شد.',

                    'dispute_allocations' => [
                        $allocation->id,
                    ],

                    'item_issue_type' => [
                        $allocation->id =>
                            'damaged',
                    ],

                    'item_description' => [
                        $allocation->id =>
                            'آسیب تستی',
                    ],
                ]
            );

        $response->assertRedirect(
            route(
                'approvals.show',
                $step
            )
        );

        $inventoryRequest->refresh();
        $allocation->refresh();
        $asset->refresh();
        $step->refresh();
        $instance->refresh();

        self::assertSame(
            'delivery_dispute',
            $inventoryRequest->status
        );

        self::assertSame(
            'delivered',
            $allocation->status
        );

        self::assertSame(
            $before['status'],
            $asset->status
        );

        self::assertSame(
            $before['custody_type'],
            $asset->custody_type
        );

        self::assertSame(
            $before['custody_user_id'],
            $asset->custody_user_id
        );

        self::assertSame(
            $before['custody_employee_id'],
            $asset->custody_employee_id
        );

        self::assertSame(
            $before['current_site_id'],
            $asset->current_site_id
        );

        self::assertSame(
            $before['current_location_id'],
            $asset->current_location_id
        );

        self::assertSame(
            'pending',
            $step->status
        );

        self::assertSame(
            (int) $step->id,
            (int) $instance->current_step_id
        );

        $dispute =
            DeliveryDispute::withoutGlobalScopes()
                ->where(
                    'inventory_request_id',
                    $inventoryRequest->id
                )
                ->first();

        self::assertNotNull($dispute);

        self::assertSame(
            'warehouse_pending',
            $dispute->status
        );

        self::assertSame(
            'damaged',
            $dispute->reason_code
        );

        $item =
            DeliveryDisputeItem::withoutGlobalScopes()
                ->where(
                    'delivery_dispute_id',
                    $dispute->id
                )
                ->first();

        self::assertNotNull($item);

        self::assertSame(
            (int) $asset->id,
            (int) $item->asset_id
        );

        self::assertSame(
            'reported',
            $item->status
        );

        self::assertSame(
            $before['custody_type'],
            $item->custody_snapshot[
                'custody_type'
            ]
            ?? null
        );
    }

    public function test_open_dispute_cannot_be_reported_twice(): void
    {
        [$user, $employee] =
            $this->authenticateRequester();

        [
            $inventoryRequest,
            $allocation,
            $asset,
            $step,
        ] =
            $this->fixture(
                $user,
                $employee
            );

        $payload = [
            'reason_code' => 'other',
            'description' => 'First dispute',
            'dispute_allocations' => [
                $allocation->id,
            ],
        ];

        $first =
            $this->post(
                route(
                    'delivery-disputes.store',
                    $step
                ),
                $payload
            );

        $first->assertSessionHasNoErrors();

        $inventoryRequest->update([
            'status' =>
                'awaiting_receipt',
        ]);

        $second =
            $this->post(
                route(
                    'delivery-disputes.store',
                    $step
                ),
                $payload
            );

        $second->assertSessionHasErrors(
            'request'
        );

        self::assertSame(
            1,
            DeliveryDispute::withoutGlobalScopes()
                ->where(
                    'inventory_request_id',
                    $inventoryRequest->id
                )
                ->count()
        );
    }

    private function authenticateRequester(): array
    {
        $user =
            User::withoutGlobalScopes()
                ->whereKey(6)
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

        self::assertTrue(
            $user->hasPermission(
                'approvals.act'
            ),
            'testadmin does not have approvals.act permission.'
        );

        $employee =
            Employee::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        self::assertNotNull(
            $employee,
            'Requester employee was not found.'
        );

        $sessionId =
            SessionId::generate();

        $loginSession =
            LoginSession::start(
                sessionId:
                    $sessionId,

                userId:
                    UserId::fromInt(
                        (int) $user->id
                    ),

                ipAddress:
                    '127.0.0.1',

                userAgent:
                    'PHPUnit Delivery Dispute Foundation',

                computerName:
                    'PHPUNIT',
            );

        $repository =
            app(
                LoginSessionRepositoryInterface::class
            );

        $repository->save(
            $loginSession
        );

        $this->actingAs(
            $user
        );

        $this->withSession([
            'domain_session_id' =>
                $sessionId->value(),

            'domain_user_id' =>
                (int) $user->id,
        ]);

        return [
            $user,
            $employee,
        ];
    }

    private function fixture(
        User $user,
        Employee $employee
    ): array {
        $category =
            AssetCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->first();

        self::assertNotNull($category);

        $type =
            AssetType::withoutGlobalScopes()
                ->where(
                    'company_id',
                    2
                )
                ->where(
                    'asset_category_id',
                    $category->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if ($type === null) {
            $type =
                new AssetType([
                    'asset_category_id' =>
                        $category->id,

                    'name' =>
                        'Dispute Test Type',

                    'code' =>
                        'DISPUTE-'
                        . strtoupper(
                            bin2hex(
                                random_bytes(3)
                            )
                        ),

                    'coding_code' =>
                        '097',

                    'is_active' =>
                        true,

                    'sort_order' =>
                        997,
                ]);

            $type->company_id = 2;
            $type->save();
        }

        $asset =
            new Asset([
                'asset_category_id' =>
                    $category->id,

                'asset_type_id' =>
                    $type->id,

                'asset_code' =>
                    '97-97-097-'
                    . strtoupper(
                        bin2hex(
                            random_bytes(3)
                        )
                    ),

                'title' =>
                    'Delivery Dispute Test Asset',

                'purchase_price' =>
                    0,

                'status' =>
                    'assigned',

                'custody_type' =>
                    'employee',

                'custody_user_id' =>
                    $user->id,

                'custody_employee_id' =>
                    $employee->id,

                'custody_department_id' =>
                    $employee->department_id,

                'current_site_id' =>
                    $employee->site_id,

                'current_location_id' =>
                    $employee->location_id,

                'is_active' =>
                    true,
            ]);

        $asset->company_id = 2;
        $asset->plate_number =
            $asset->asset_code;
        $asset->save();

        $inventoryRequest =
            new InventoryRequest([
                'request_number' =>
                    'DISPUTE-'
                    . strtoupper(
                        bin2hex(
                            random_bytes(5)
                        )
                    ),

                'requester_employee_id' =>
                    $employee->id,

                'requester_user_id' =>
                    $user->id,

                'site_id' =>
                    $employee->site_id,

                'department_id' =>
                    $employee->department_id,

                'status' =>
                    'awaiting_receipt',

                'priority' =>
                    'normal',

                'purpose' =>
                    'Delivery dispute foundation test',

                'submitted_at' =>
                    now(),

                'approved_at' =>
                    now(),
            ]);

        $inventoryRequest->company_id = 2;
        $inventoryRequest->save();

        $requestItem =
            InventoryRequestItem::query()
                ->create([
                    'inventory_request_id' =>
                        $inventoryRequest->id,

                    'asset_category_id' =>
                        $category->id,

                    'asset_type_id' =>
                        $type->id,

                    'item_code' =>
                        'DISPUTE-ITEM',

                    'item_name' =>
                        'Dispute Item',

                    'unit' =>
                        'عدد',

                    'requested_quantity' =>
                        1,

                    'approved_quantity' =>
                        1,

                    'fulfilled_quantity' =>
                        1,

                    'status' =>
                        'fulfilled',

                    'sort_order' =>
                        1,
                ]);

        $instance =
            new WorkflowInstance([
                'workflow_id' =>
                    null,

                'workflow_name' =>
                    'Delivery Dispute Test Workflow',

                'workflow_code' =>
                    'DISPUTE-TEST',

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
                    $user->id,

                'status' =>
                    'pending',

                'current_step_id' =>
                    null,

                'started_at' =>
                    now(),
            ]);

        $instance->company_id = 2;
        $instance->save();

        $step =
            WorkflowInstanceStep::query()
                ->create([
                    'workflow_instance_id' =>
                        $instance->id,

                    'workflow_step_id' =>
                        null,

                    'name' =>
                        'تأیید دریافت توسط درخواست‌کننده',

                    'code' =>
                        'REQUESTER-RECEIPT',

                    'step_type' =>
                        'approval',

                    'approver_type' =>
                        'requester',

                    'approver_reference_id' =>
                        null,

                    'sort_order' =>
                        50,

                    'is_required' =>
                        true,

                    'rejection_action' =>
                        'return_previous',

                    'resolved_employee_id' =>
                        $employee->id,

                    'resolved_user_id' =>
                        $user->id,

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
            new InventoryRequestAllocation([
                'inventory_request_id' =>
                    $inventoryRequest->id,

                'inventory_request_item_id' =>
                    $requestItem->id,

                'asset_id' =>
                    $asset->id,

                'status' =>
                    'delivered',

                'reserved_by_user_id' =>
                    $user->id,

                'reserved_by_employee_id' =>
                    $employee->id,

                'reserved_at' =>
                    now(),

                'approved_at' =>
                    now(),

                'delivered_at' =>
                    now(),

                'note' =>
                    'Delivery dispute foundation allocation',
            ]);

        $allocation->company_id = 2;
        $allocation->save();

        return [
            $inventoryRequest,
            $allocation,
            $asset,
            $step,
            $instance,
        ];
    }
}