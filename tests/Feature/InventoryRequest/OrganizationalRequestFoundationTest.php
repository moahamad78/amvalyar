<?php

declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Models\InventoryRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class OrganizationalRequestFoundationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_request_has_organizational_delivery_target_columns(): void
    {
        self::assertTrue(
            Schema::hasColumns(
                'inventory_requests',
                [
                    'delivery_target_type',
                    'target_site_id',
                    'target_department_id',
                    'target_location_id',
                ]
            )
        );
    }

    public function test_inventory_request_model_exposes_target_fields(): void
    {
        $model = new InventoryRequest();

        foreach (
            [
                'delivery_target_type',
                'target_site_id',
                'target_department_id',
                'target_location_id',
            ]
            as $field
        ) {
            self::assertContains(
                $field,
                $model->getFillable()
            );
        }

        self::assertSame(
            'target_site_id',
            $model->targetSite()->getForeignKeyName()
        );

        self::assertSame(
            'target_department_id',
            $model->targetDepartment()->getForeignKeyName()
        );

        self::assertSame(
            'target_location_id',
            $model->targetLocation()->getForeignKeyName()
        );
    }

    public function test_final_delivery_ui_contains_personal_and_organizational_paths(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/final_warehouse_deliveries/show.blade.php'
            )
        );

        self::assertIsString($view);
        self::assertStringContainsString(
            'استقرار به‌عنوان مال سازمانی',
            $view
        );
        self::assertStringContainsString(
            'تحویل به شخص درخواست‌کننده',
            $view
        );
        self::assertStringContainsString(
            'پیش‌نمایش و چاپ پلاک کالاهای این درخواست',
            $view
        );

        self::assertSame(
            1,
            substr_count(
                $view,
                "final-warehouse-deliveries.plates.preview"
            )
        );
    }

    public function test_final_delivery_service_routes_organizational_requests_to_organization_custody(): void
    {
        $service = file_get_contents(
            app_path(
                'Services/FinalWarehouseDeliveryService.php'
            )
        );

        self::assertIsString($service);
        self::assertStringContainsString(
            "\$deliveryTargetType === 'organization'",
            $service
        );
        self::assertStringContainsString(
            'assignToOrganization(',
            $service
        );
        self::assertStringContainsString(
            'assignToEmployee(',
            $service
        );
    }
}