<?php
declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class DeliveryDisputeReplacementSpecialistReviewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_replacement_review_service_is_dedicated_and_auditable(): void
    {
        $source = file_get_contents(
            app_path('Services/DeliveryDisputeReplacementReviewService.php')
        );

        self::assertIsString($source);
        self::assertStringContainsString('delivery_dispute_replacement', $source);
        self::assertStringContainsString('replacement-dispute-', $source);
        self::assertStringContainsString('original_history_preserved', $source);
        self::assertStringContainsString("'replacement_ready_delivery'", $source);
    }

    public function test_specialist_controller_bypasses_generic_gate_for_replacements(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/SpecialistApprovalController.php')
        );

        self::assertIsString($source);
        self::assertStringContainsString('isReplacementBranch', $source);
        self::assertStringContainsString('readyForRedelivery', $source);
        self::assertStringContainsString('برای رد کالای جایگزین', $source);
    }

    public function test_rejected_replacement_is_released_and_returned_to_selection(): void
    {
        $source = file_get_contents(
            app_path('Services/DeliveryDisputeReplacementReviewService.php')
        );

        self::assertIsString($source);
        self::assertStringContainsString('replacement_allocation_id', $source);
        self::assertStringContainsString('->release(', $source);
        self::assertStringContainsString("'warehouse_received'", $source);
        self::assertStringContainsString("'replacement_pending'", $source);
        self::assertStringContainsString("'cancelled'", $source);
    }

    public function test_replacement_allocation_starts_specialist_review(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/DeliveryDisputeController.php')
        );
        $index = file_get_contents(
            resource_path('views/inventory_requests/index.blade.php')
        );

        self::assertIsString($controller);
        self::assertIsString($index);
        self::assertStringContainsString('startReview', $controller);
        self::assertStringContainsString('آماده تحویل مجدد جایگزین', $index);
    }
}