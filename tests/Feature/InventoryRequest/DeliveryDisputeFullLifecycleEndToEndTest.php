<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Http\Middleware\FinalizeRequesterReceiptOnApproval;
use App\Models\Asset;
use App\Models\AssetCategoryApprovalRoute;
use App\Models\AssetCategoryCodingMapping;
use App\Models\AssetCodeFormulaSetting;
use App\Models\AssetCodeSequence;
use App\Models\AssetTransaction;
use App\Models\AssetType;
use App\Models\DeliveryDispute;
use App\Models\DeliveryDisputeItem;
use App\Models\Employee;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestAllocation;
use App\Models\InventoryRequestItem;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceBranch;
use App\Models\WorkflowInstanceStep;
use App\Services\AssetCode\AssetCodeIssuanceService;
use App\Services\AssetCustodyService;
use App\Services\DeliveryDisputeReplacementDeliveryService;
use App\Services\DeliveryDisputeReplacementReviewService;
use App\Services\FinalWarehouseDeliveryService;
use App\Services\InventoryAssetAllocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class DeliveryDisputeFullLifecycleEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    public function test_real_delivery_dispute_replacement_cycle_closes_without_double_counting(): void
    {
        $actor = $this->actorUser();
        $employee = $this->recipientEmployee($actor);

        [$original, $site] = $this->newWarehouseAssetForOfficialIssuance();

        $original = app(AssetCodeIssuanceService::class)->issue(
            asset: $original,
            codingSiteId: (int) $site->id,
            actor: $actor,
        );

        $replacement = Asset::withoutGlobalScopes()->create([
            'company_id' => 2,
            'asset_category_id' => $original->asset_category_id,
            'asset_type_id' => $original->asset_type_id,
            'asset_code' => null,
            'title' => 'E2E Replacement Asset',
            'purchase_price' => 0,
            'status' => 'warehouse',
            'plate_number' => null,
            'custody_type' => AssetCustodyService::TYPE_WAREHOUSE,
            'is_active' => true,
        ]);

        $replacement = app(AssetCodeIssuanceService::class)->issue(
            asset: $replacement,
            codingSiteId: (int) $site->id,
            actor: $actor,
        );

        [
            $inventoryRequest,
            $item,
            $oldAllocation,
            $instance,
            $finalStep,
        ] = $this->deliveryFixture(
            asset: $original,
            actor: $actor,
            employee: $employee,
        );

        $receiptStep = WorkflowInstanceStep::query()->create([
            'workflow_instance_id' => $instance->id,
            'workflow_step_id' => null,
            'name' => 'تأیید دریافت درخواست‌کننده',
            'code' => 'REQUESTER-RECEIPT',
            'step_type' => 'action',
            'approver_type' => 'requester',
            'approver_reference_id' => null,
            'sort_order' => 110,
            'is_required' => true,
            'rejection_action' => 'terminate',
            'resolved_employee_id' => $employee->id,
            'resolved_user_id' => $actor->id,
            'status' => 'waiting',
            'activated_at' => null,
        ]);

        app(FinalWarehouseDeliveryService::class)->deliver(
            step: $finalStep,
            actorUser: $actor,
            httpRequest: Request::create('/test/final-delivery', 'POST'),
        );

        $inventoryRequest->refresh();
        $item->refresh();
        $oldAllocation->refresh();
        $original->refresh();

        self::assertSame('awaiting_receipt', $inventoryRequest->status);
        self::assertSame('1.000', (string) $item->fulfilled_quantity);
        self::assertSame('delivered', $oldAllocation->status);
        self::assertSame('assigned', $original->status);

        $finalStep->update([
            'status' => 'approved',
            'acted_at' => now(),
            'acted_by_employee_id' => $employee->id,
            'acted_by_user_id' => $actor->id,
        ]);

        $receiptStep->update([
            'status' => 'pending',
            'activated_at' => now(),
        ]);

        $instance->update([
            'current_step_id' => $receiptStep->id,
        ]);

        $dispute = new DeliveryDispute([
            'inventory_request_id' => $inventoryRequest->id,
            'workflow_instance_id' => $instance->id,
            'requester_receipt_step_id' => $receiptStep->id,
            'reported_by_user_id' => $actor->id,
            'reported_by_employee_id' => $employee->id,
            'status' => 'warehouse_pending',
            'reason_code' => 'wrong_or_faulty_asset',
            'description' => 'E2E replacement lifecycle dispute',
            'reported_at' => now(),
        ]);
        $dispute->company_id = 2;
        $dispute->save();

        $disputeItem = new DeliveryDisputeItem([
            'delivery_dispute_id' => $dispute->id,
            'inventory_request_allocation_id' => $oldAllocation->id,
            'inventory_request_item_id' => $item->id,
            'asset_id' => $original->id,
            'issue_type' => 'wrong_or_faulty_asset',
            'description' => 'E2E disputed original asset',
            'custody_snapshot' => [
                'custody_type' => $original->custody_type,
                'custody_user_id' => $original->custody_user_id,
                'custody_employee_id' => $original->custody_employee_id,
            ],
            'status' => 'reported',
        ]);
        $disputeItem->company_id = 2;
        $disputeItem->save();

        $inventoryRequest->update([
            'status' => 'delivery_dispute',
            'fulfilled_at' => null,
        ]);

        $returnTransaction = app(AssetCustodyService::class)
            ->returnDeliveredAssetToWarehouse(
                asset: $original->fresh(),
                actorUser: $actor,
                description: 'E2E disputed asset returned to warehouse',
            );

        $oldAllocation->update([
            'status' => 'returned',
            'note' => 'E2E original allocation returned after dispute',
        ]);

        $disputeItem->update([
            'status' => 'warehouse_received',
        ]);

        $dispute->update([
            'status' => 'warehouse_received',
            'warehouse_received_at' => now(),
        ]);

        $inventoryRequest->update([
            'status' => 'replacement_pending',
            'fulfilled_at' => null,
        ]);

        $original->refresh();

        self::assertSame(AssetCustodyService::TYPE_WAREHOUSE, $original->custody_type);
        self::assertSame('warehouse', $original->status);
        self::assertSame('return', $returnTransaction->type);

        $newAllocation = app(InventoryAssetAllocationService::class)->reserve(
            request: $inventoryRequest->fresh(),
            item: $item->fresh(),
            asset: $replacement->fresh(),
            actor: $actor,
        );

        $newAllocation->update([
            'status' => 'approved',
            'approved_at' => now(),
            'note' => 'E2E replacement allocation approved',
        ]);

        $disputeItem->update([
            'replacement_asset_id' => $replacement->id,
            'replacement_allocation_id' => $newAllocation->id,
            'replacement_selected_at' => now(),
            'status' => 'replacement_allocated',
        ]);

        $dispute->update([
            'status' => 'replacement_allocated',
        ]);

        $inventoryRequest->update([
            'status' => 'replacement_review_pending',
            'fulfilled_at' => null,
        ]);

        AssetCategoryApprovalRoute::withoutGlobalScopes()
            ->where('company_id', 2)
            ->where('asset_category_id', $replacement->asset_category_id)
            ->where('process_type', 'inventory_request')
            ->update([
                'is_active' => false,
            ]);

        AssetCategoryApprovalRoute::withoutGlobalScopes()->create([
            'company_id' => 2,
            'asset_category_id' => $replacement->asset_category_id,
            'process_type' => 'inventory_request',
            'approver_type' => 'employee',
            'approver_reference_id' => $employee->id,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 10,
            'settings' => [
                'source' => 'e2e_test',
            ],
        ]);

        $reviewService = app(DeliveryDisputeReplacementReviewService::class);

        $review = $reviewService->startReview(
            deliveryDispute: $dispute->fresh(),
            inventoryRequest: $inventoryRequest->fresh(),
            actorUser: $actor,
        );

        self::assertFalse($review['ready_for_redelivery']);
        self::assertSame(1, $review['required_branch_count']);

        $branch = WorkflowInstanceBranch::withoutGlobalScopes()
            ->where('workflow_instance_id', $instance->id)
            ->where('settings->context', DeliveryDisputeReplacementReviewService::CONTEXT)
            ->where('settings->delivery_dispute_id', $dispute->id)
            ->where('is_required', true)
            ->firstOrFail();

        self::assertTrue(
            $reviewService->approve(
                branch: $branch,
                actorEmployee: $employee,
                actorUser: $actor,
                comment: 'E2E specialist approved replacement asset',
            )
        );

        $dispute->refresh();
        $disputeItem->refresh();
        $inventoryRequest->refresh();

        self::assertSame('replacement_review_approved', $dispute->status);
        self::assertSame('replacement_approved', $disputeItem->status);
        self::assertSame('replacement_ready_delivery', $inventoryRequest->status);

        app(DeliveryDisputeReplacementDeliveryService::class)->deliver(
            deliveryDispute: $dispute,
            actorUser: $actor,
            actorEmployee: $employee,
            note: 'E2E controlled replacement redelivery',
        );

        $replacement->refresh();
        $newAllocation->refresh();
        $dispute->refresh();
        $disputeItem->refresh();
        $inventoryRequest->refresh();
        $item->refresh();
        $receiptStep->refresh();
        $instance->refresh();

        self::assertSame('assigned', $replacement->status);
        self::assertSame(AssetCustodyService::TYPE_EMPLOYEE, $replacement->custody_type);
        self::assertSame((int) $employee->id, (int) $replacement->custody_employee_id);
        self::assertSame('delivered', $newAllocation->status);
        self::assertSame('replacement_delivered', $dispute->status);
        self::assertSame('replacement_delivered', $disputeItem->status);
        self::assertSame('awaiting_receipt', $inventoryRequest->status);
        self::assertSame('1.000', (string) $item->fulfilled_quantity);
        self::assertSame('pending', $receiptStep->status);
        self::assertSame((int) $receiptStep->id, (int) $instance->current_step_id);

        $deliveryTransactionCount = AssetTransaction::withoutGlobalScopes()
            ->whereIn('asset_id', [
                $original->id,
                $replacement->id,
            ])
            ->where('type', 'delivery')
            ->count();

        self::assertSame(2, $deliveryTransactionCount);

        $request = Request::create('/test/requester-receipt', 'POST', [
            'action' => 'approve',
        ]);

        $request->setRouteResolver(
            fn () => new class($receiptStep)
            {
                public function __construct(
                    private readonly WorkflowInstanceStep $step
                ) {
                }

                public function parameter(string $key): mixed
                {
                    return $key === 'step'
                        ? $this->step
                        : null;
                }
            }
        );

        $response = app(FinalizeRequesterReceiptOnApproval::class)->handle(
            $request,
            function () use ($receiptStep): Response {
                $receiptStep->update([
                    'status' => 'approved',
                    'acted_at' => now(),
                ]);

                return response('ok');
            }
        );

        self::assertSame(200, $response->getStatusCode());

        $inventoryRequest->refresh();
        $item->refresh();
        $dispute->refresh();
        $disputeItem->refresh();
        $receiptStep->refresh();

        self::assertSame('fulfilled', $inventoryRequest->status);
        self::assertNotNull($inventoryRequest->fulfilled_at);
        self::assertSame('1.000', (string) $item->fulfilled_quantity);
        self::assertSame('resolved', $dispute->status);
        self::assertNotNull($dispute->resolved_at);
        self::assertSame('resolved', $disputeItem->status);
        self::assertSame('approved', $receiptStep->status);

        $oldAllocation->refresh();
        $newAllocation->refresh();

        self::assertSame('returned', $oldAllocation->status);
        self::assertSame('delivered', $newAllocation->status);
        self::assertSame($original->asset_code, $returnTransaction->plate_number);
        self::assertSame($replacement->asset_code, $replacement->plate_number);
    }

    private function newWarehouseAssetForOfficialIssuance(): array
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
            'name' => 'E2E Dispute Lifecycle Coding Site',
            'code' => '81',
            'type' => 'factory',
            'is_active' => true,
            'sort_order' => 981,
        ]);

        AssetCategoryCodingMapping::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => 2,
                'asset_category_id' => 1,
            ],
            [
                'coding_code' => '82',
                'is_active' => true,
            ]
        );

        $type = AssetType::withoutGlobalScopes()->create([
            'company_id' => 2,
            'asset_category_id' => 1,
            'name' => 'E2E Dispute Lifecycle Type',
            'code' => 'E2E-DISPUTE-LIFECYCLE-' . strtoupper(bin2hex(random_bytes(3))),
            'coding_code' => '083',
            'description' => 'Full delivery dispute replacement lifecycle test type.',
            'is_active' => true,
            'sort_order' => 981,
        ]);

        AssetCodeSequence::query()
            ->where('company_id', 2)
            ->where('prefix', '81-82-083')
            ->delete();

        $asset = Asset::withoutGlobalScopes()->create([
            'company_id' => 2,
            'asset_category_id' => 1,
            'asset_type_id' => $type->id,
            'asset_code' => null,
            'title' => 'E2E Original Disputed Asset',
            'purchase_price' => 0,
            'status' => 'warehouse',
            'plate_number' => null,
            'custody_type' => AssetCustodyService::TYPE_WAREHOUSE,
            'is_active' => true,
        ]);

        return [$asset, $site];
    }

    private function deliveryFixture(
        Asset $asset,
        User $actor,
        Employee $employee
    ): array {
        $inventoryRequest = InventoryRequest::withoutGlobalScopes()->create([
            'company_id' => 2,
            'request_number' => 'E2E-DISPUTE-' . strtoupper(bin2hex(random_bytes(6))),
            'requester_employee_id' => $employee->id,
            'requester_user_id' => $actor->id,
            'site_id' => $employee->site_id,
            'department_id' => $employee->department_id,
            'workflow_instance_id' => null,
            'delivery_target_type' => 'employee',
            'status' => 'approved',
            'priority' => 'normal',
            'purpose' => 'Full delivery dispute replacement E2E test',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $item = InventoryRequestItem::query()->create([
            'inventory_request_id' => $inventoryRequest->id,
            'asset_category_id' => $asset->asset_category_id,
            'asset_type_id' => $asset->asset_type_id,
            'item_code' => 'E2E-DISPUTE-ITEM',
            'item_name' => 'E2E Dispute Lifecycle Item',
            'unit' => 'عدد',
            'requested_quantity' => 1,
            'approved_quantity' => 1,
            'fulfilled_quantity' => 0,
            'status' => 'approved',
            'sort_order' => 1,
        ]);

        $instance = WorkflowInstance::withoutGlobalScopes()->create([
            'company_id' => 2,
            'workflow_id' => null,
            'workflow_name' => 'E2E Delivery Dispute Lifecycle',
            'workflow_code' => 'E2E-DELIVERY-DISPUTE-LIFECYCLE',
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
            'workflow_instance_id' => $instance->id,
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

        $instance->update([
            'current_step_id' => $step->id,
        ]);

        $inventoryRequest->update([
            'workflow_instance_id' => $instance->id,
        ]);

        $allocation = InventoryRequestAllocation::query()->create([
            'company_id' => 2,
            'inventory_request_id' => $inventoryRequest->id,
            'inventory_request_item_id' => $item->id,
            'asset_id' => $asset->id,
            'status' => 'approved',
            'reserved_by_user_id' => $actor->id,
            'reserved_by_employee_id' => $employee->id,
            'reserved_at' => now(),
            'approved_at' => now(),
            'note' => 'E2E original approved allocation',
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
        $user = User::query()
            ->whereKey(6)
            ->where('company_id', 2)
            ->where('username', 'testadmin')
            ->where('is_active', true)
            ->first();

        self::assertNotNull(
            $user,
            'Seeded company 2 testadmin user is required for isolated E2E tests.'
        );

        return $user;
    }

    private function recipientEmployee(User $actor): Employee
    {
        $employee = Employee::withoutGlobalScopes()
            ->where('company_id', 2)
            ->where('user_id', $actor->id)
            ->where('is_active', true)
            ->first();

        self::assertNotNull(
            $employee,
            'An active employee linked to company 2 testadmin is required.'
        );

        return $employee;
    }
}
