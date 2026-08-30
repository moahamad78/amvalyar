<?php

declare(strict_types=1);

namespace Tests\Feature\AssetRepair;

use App\Services\NavigationService;
use App\Services\PermissionRegistryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AssetRepairUiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_repair_routes_are_registered_with_expected_permissions(): void
    {
        $expected = [
            'asset-repairs.index' => 'asset_repairs.view',
            'asset-repairs.create' => 'asset_repairs.create',
            'asset-repairs.store' => 'asset_repairs.create',
            'asset-repairs.show' => 'asset_repairs.view',
            'asset-repairs.submit' => 'asset_repairs.create',
            'asset-repairs.start' => 'asset_repairs.manage',
            'asset-repairs.complete' => 'asset_repairs.manage',
        ];

        $routes = collect(Route::getRoutes()->getRoutes())
            ->keyBy(fn ($route) => $route->getName());

        foreach ($expected as $name => $permission) {
            $this->assertTrue($routes->has($name), "Missing route [$name].");
            $this->assertContains(
                "permission:$permission",
                $routes[$name]->gatherMiddleware(),
                "Route [$name] is missing permission [$permission]."
            );
        }
    }

    public function test_repair_permissions_are_registered(): void
    {
        $names = app(PermissionRegistryService::class)->registeredNames();

        $this->assertContains('asset_repairs.view', $names);
        $this->assertContains('asset_repairs.create', $names);
        $this->assertContains('asset_repairs.manage', $names);
    }

    public function test_repair_views_exist(): void
    {
        $this->assertTrue(view()->exists('asset_repairs.index'));
        $this->assertTrue(view()->exists('asset_repairs.create'));
        $this->assertTrue(view()->exists('asset_repairs.show'));
    }

    public function test_navigation_service_contains_repair_workspace_contract(): void
    {
        $source = file_get_contents(app_path('Services/NavigationService.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("'asset-repairs.index'", $source);
        $this->assertStringContainsString("'asset-repairs.*'", $source);
        $this->assertStringContainsString("'asset_repairs.view'", $source);
    }
}