<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class RequesterReceiptWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_every_inventory_request_workflow_has_requester_receipt_after_final_warehouse_delivery(): void
    {
        $workflows =
            Workflow::withoutGlobalScopes()
                ->where(
                    'process_type',
                    'inventory_request'
                )
                ->get();

        self::assertNotEmpty($workflows);

        foreach ($workflows as $workflow) {
            $final =
                WorkflowStep::query()
                    ->where(
                        'workflow_id',
                        $workflow->id
                    )
                    ->where(
                        'code',
                        'FINAL-WAREHOUSE-DELIVERY'
                    )
                    ->first();

            $receipt =
                WorkflowStep::query()
                    ->where(
                        'workflow_id',
                        $workflow->id
                    )
                    ->where(
                        'code',
                        'REQUESTER-RECEIPT'
                    )
                    ->first();

            self::assertNotNull(
                $final,
                'FINAL-WAREHOUSE-DELIVERY missing.'
            );

            self::assertNotNull(
                $receipt,
                'REQUESTER-RECEIPT missing.'
            );

            self::assertSame(
                'requester',
                $receipt->approver_type
            );

            self::assertTrue(
                (bool) $receipt->is_required
            );

            self::assertGreaterThan(
                (int) $final->sort_order,
                (int) $receipt->sort_order
            );
        }
    }

    public function test_receipt_foundation_is_wired_into_delivery_route_and_ui(): void
    {
        $routes =
            file_get_contents(
                base_path('routes/web.php')
            );

        $service =
            file_get_contents(
                app_path(
                    'Services/FinalWarehouseDeliveryService.php'
                )
            );

        $approvalView =
            file_get_contents(
                resource_path(
                    'views/approvals/show.blade.php'
                )
            );

        $indexView =
            file_get_contents(
                resource_path(
                    'views/inventory_requests/index.blade.php'
                )
            );

        self::assertIsString($routes);
        self::assertIsString($service);
        self::assertIsString($approvalView);
        self::assertIsString($indexView);

        self::assertStringContainsString(
            'FinalizeRequesterReceiptOnApproval',
            $routes
        );

        self::assertStringContainsString(
            "'REQUESTER-RECEIPT'",
            $service
        );

        self::assertStringContainsString(
            "'awaiting_receipt'",
            $service
        );

        self::assertStringContainsString(
            "REQUESTER-RECEIPT",
            $approvalView
        );

        self::assertStringContainsString(
            'تأیید دریافت و پایان درخواست',
            $approvalView
        );

        self::assertStringContainsString(
            'در انتظار تأیید دریافت',
            $indexView
        );
    }

    public function test_receipt_rejection_is_not_routed_through_unsafe_generic_return_previous(): void
    {
        $middleware =
            file_get_contents(
                app_path(
                    'Http/Middleware/FinalizeRequesterReceiptOnApproval.php'
                )
            );

        self::assertIsString($middleware);

        self::assertStringContainsString(
            "\$action === 'reject'",
            $middleware
        );

        self::assertStringContainsString(
            'مسیر تخصصی برگشت به انبار',
            $middleware
        );
    }
}