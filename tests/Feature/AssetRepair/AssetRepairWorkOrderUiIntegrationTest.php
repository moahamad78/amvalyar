<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Services\AssetRepairWorkOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AssetRepairWorkOrderUiIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_existing_manage_routes_remain_the_only_operational_write_paths(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());
        foreach (['asset-repairs.start', 'asset-repairs.complete'] as $name) {
            $this->assertTrue($routes->has($name));
            $this->assertContains('permission:asset_repairs.manage', $routes[$name]->gatherMiddleware());
        }
    }

    public function test_show_page_exposes_complete_work_order_form_contract(): void
    {
        $view = file_get_contents(resource_path('views/asset_repairs/show.blade.php'));
        foreach (['repair_type', 'assigned_employee_id', 'external_provider_name', 'expected_return_at', 'outcome', 'labor_cost', 'parts_cost', 'external_service_cost'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $view);
        }
        $this->assertStringContainsString('$workOrder->work_order_number', $view);
        $this->assertStringContainsString('$workOrder->total_cost', $view);
    }

    public function test_controller_uses_tenant_scoped_employee_and_canonical_services(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/AssetRepairRequestController.php'));
        $this->assertStringContainsString("->where('company_id', ".'$repair->company_id)', $source);
        $this->assertStringContainsString('completeRepairWithCosts(', $source);
        $this->assertStringNotContainsString('app(AssetRepairWorkOrderService::class)', $source);
        $this->assertStringContainsString('outcome:', $source);
        $this->assertStringNotContainsString("'actual_cost' => ['nullable'", $source);
    }

    public function test_work_order_service_has_guarded_cost_update_contract(): void
    {
        $method = new \ReflectionMethod(AssetRepairWorkOrderService::class, 'updateCosts');
        $this->assertTrue($method->isPublic());
        $source = file_get_contents(app_path('Services/AssetRepairWorkOrderService.php'));
        $this->assertStringContainsString('STATUS_IN_PROGRESS', $source);
        $this->assertStringContainsString("->where('company_id', ".'$repair->company_id)', $source);
        $this->assertStringContainsString("'updated_by_user_id' => ".'$actor->id', $source);
    }
}
