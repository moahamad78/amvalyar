<?php
declare(strict_types=1);

namespace Tests\Feature\InventoryRequest;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\User;
use App\Services\AssetCustodyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class DeliveryDisputeWarehouseReceiptTest extends TestCase
{
    use DatabaseTransactions;

    public function test_warehouse_receipt_route_exists(): void
    {
        self::assertTrue(Route::has('delivery-disputes.warehouse-receive'));
    }

    public function test_return_service_moves_assigned_asset_to_warehouse_and_records_return(): void
    {
        $user = User::withoutGlobalScopes()
            ->where('company_id', 2)
            ->where('is_active', true)
            ->first();

        $employee = Employee::withoutGlobalScopes()
            ->where('company_id', 2)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->first();

        $asset = Asset::withoutGlobalScopes()
            ->where('company_id', 2)
            ->where('status', 'warehouse')
            ->whereNotNull('asset_code')
            ->first();

        self::assertNotNull($user);
        self::assertNotNull($employee);
        self::assertNotNull($asset);

        $asset->status = 'assigned';
        $asset->custody_type = AssetCustodyService::TYPE_EMPLOYEE;
        $asset->custody_user_id = $employee->user_id;
        $asset->custody_employee_id = $employee->id;
        $asset->custody_department_id = $employee->department_id;
        $asset->current_site_id = $employee->site_id;
        $asset->current_location_id = $employee->location_id;
        $asset->save();

        $tx = app(AssetCustodyService::class)
            ->returnDeliveredAssetToWarehouse(
                asset: $asset,
                actorUser: $user,
                description: 'test'
            );

        $asset->refresh();

        self::assertSame('warehouse', $asset->status);
        self::assertSame(AssetCustodyService::TYPE_WAREHOUSE, $asset->custody_type);
        self::assertNull($asset->custody_user_id);
        self::assertNull($asset->custody_employee_id);
        self::assertSame('return', $tx->type);
        self::assertSame($asset->asset_code, $tx->plate_number);
        self::assertSame(AssetCustodyService::TYPE_WAREHOUSE, $tx->to_custody_type);
    }

    public function test_replacement_pending_foundation_is_wired(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DeliveryDisputeController.php'));
        $view = file_get_contents(resource_path('views/delivery_disputes/show.blade.php'));
        $index = file_get_contents(resource_path('views/inventory_requests/index.blade.php'));

        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertIsString($index);

        self::assertStringContainsString("'returned'", $controller);
        self::assertStringContainsString("'warehouse_received'", $controller);
        self::assertStringContainsString("'replacement_pending'", $controller);
        self::assertStringContainsString('returnDeliveredAssetToWarehouse', $controller);
        self::assertStringContainsString('دریافت فیزیکی توسط انبار', $view);
        self::assertStringContainsString('در انتظار کالای جایگزین', $index);
    }
}