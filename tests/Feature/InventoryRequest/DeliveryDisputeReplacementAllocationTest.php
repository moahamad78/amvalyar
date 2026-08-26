<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Models\DeliveryDisputeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DeliveryDisputeReplacementAllocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_replacement_allocation_route_exists(): void
    {
        self::assertTrue(
            Route::has(
                'delivery-disputes.allocate-replacements'
            )
        );
    }

    public function test_delivery_dispute_items_have_replacement_link_columns(): void
    {
        self::assertTrue(
            Schema::hasColumns(
                'delivery_dispute_items',
                [
                    'replacement_asset_id',
                    'replacement_allocation_id',
                    'replacement_selected_at',
                ]
            )
        );
    }

    public function test_replacement_foundation_preserves_old_allocation_and_creates_new_link(): void
    {
        $controller = file_get_contents(
            app_path(
                'Http/Controllers/DeliveryDisputeController.php'
            )
        );

        $model = file_get_contents(
            app_path(
                'Models/DeliveryDisputeItem.php'
            )
        );

        $view = file_get_contents(
            resource_path(
                'views/delivery_disputes/show.blade.php'
            )
        );

        self::assertIsString($controller);
        self::assertIsString($model);
        self::assertIsString($view);

        self::assertStringContainsString(
            'replacement_allocation_id',
            $controller
        );

        self::assertStringContainsString(
            "'replacement_allocated'",
            $controller
        );

        self::assertStringContainsString(
            "'replacement_review_pending'",
            $controller
        );

        self::assertStringContainsString(
            'InventoryAssetAllocationService',
            $controller
        );

        self::assertStringNotContainsString(
            "->delete()",
            $controller
        );

        self::assertStringContainsString(
            'replacementAsset',
            $model
        );

        self::assertStringContainsString(
            'تخصیص کالای جایگزین',
            $view
        );
    }
}