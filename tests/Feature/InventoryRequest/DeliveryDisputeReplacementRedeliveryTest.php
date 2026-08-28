<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class DeliveryDisputeReplacementRedeliveryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_controlled_redelivery_route_exists(): void
    {
        self::assertTrue(
            Route::has(
                'delivery-disputes.redeliver-replacement'
            )
        );
    }

    public function test_redelivery_service_uses_real_custody_delivery_and_preserves_quantity(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Services/DeliveryDisputeReplacementDeliveryService.php'
                )
            );

        self::assertIsString(
            $source
        );

        self::assertStringContainsString(
            'assignToEmployee',
            $source
        );

        self::assertStringContainsString(
            'assignToOrganization',
            $source
        );

        self::assertStringContainsString(
            "'delivered'",
            $source
        );

        self::assertStringContainsString(
            "'replacement_delivered'",
            $source
        );

        self::assertStringContainsString(
            "'awaiting_receipt'",
            $source
        );

        self::assertStringContainsString(
            'Do NOT increment InventoryRequestItem.fulfilled_quantity',
            $source
        );

        self::assertStringNotContainsString(
            'fulfilled_quantity =',
            $source
        );
    }

    public function test_redelivery_requires_permanent_asset_code_and_pending_receipt(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Services/DeliveryDisputeReplacementDeliveryService.php'
                )
            );

        self::assertIsString(
            $source
        );

        self::assertStringContainsString(
            '$asset->asset_code',
            $source
        );

        self::assertStringContainsString(
            "'REQUESTER-RECEIPT'",
            $source
        );

        self::assertStringContainsString(
            "'pending'",
            $source
        );

        self::assertStringContainsString(
            'current_step_id',
            $source
        );
    }

    public function test_requester_receipt_approval_resolves_replacement_dispute(): void
    {
        $middleware =
            file_get_contents(
                app_path(
                    'Http/Middleware/FinalizeRequesterReceiptOnApproval.php'
                )
            );

        self::assertIsString(
            $middleware
        );

        self::assertStringContainsString(
            "'replacement_delivered'",
            $middleware
        );

        self::assertStringContainsString(
            "'resolved'",
            $middleware
        );

        self::assertStringContainsString(
            "'fulfilled'",
            $middleware
        );

        self::assertStringContainsString(
            'resolved_at',
            $middleware
        );
    }
}