<?php

declare(strict_types=1);

namespace Tests\Feature\AssetMovement;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class LegacyAssetTransactionWritePathHardeningTest extends TestCase
{
    public function test_legacy_create_route_is_a_compatibility_redirect_not_a_direct_write_ui(): void
    {
        $route = Route::getRoutes()->getByName('asset-transactions.create');

        $this->assertNotNull($route);
        $this->assertSame(
            'asset-transactions/create',
            $route->uri()
        );

        $action = $route->getAction();

        $this->assertArrayHasKey('uses', $action);
        $this->assertInstanceOf(
            \Closure::class,
            $action['uses']
        );
    }

    public function test_legacy_store_route_is_a_compatibility_guard_not_the_transaction_controller(): void
    {
        $route = Route::getRoutes()->getByName('asset-transactions.store');

        $this->assertNotNull($route);
        $this->assertSame(
            'asset-transactions',
            $route->uri()
        );

        $action = $route->getAction();

        $this->assertArrayHasKey('uses', $action);
        $this->assertInstanceOf(
            \Closure::class,
            $action['uses']
        );
    }

    public function test_legacy_controller_is_no_longer_reachable_from_registered_write_routes(): void
    {
        foreach ([
            'asset-transactions.create',
            'asset-transactions.store',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route);

            $uses = $route->getAction()['uses'] ?? null;

            $this->assertFalse(
                is_string($uses)
                && str_contains(
                    $uses,
                    'AssetTransactionController'
                ),
                $name . ' must not expose the legacy direct mutation controller.'
            );
        }
    }

    public function test_modern_movement_request_routes_remain_registered_and_permission_protected(): void
    {
        $expected = [
            'asset-movement-requests.index'
                => 'permission:asset_movement_requests.view',
            'asset-movement-requests.create'
                => 'permission:asset_movement_requests.create',
            'asset-movement-requests.store'
                => 'permission:asset_movement_requests.create',
            'asset-movement-requests.show'
                => 'permission:asset_movement_requests.view',
        ];

        foreach ($expected as $name => $permission) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, $name);

            $middleware = $route->gatherMiddleware();

            $this->assertContains(
                $permission,
                $middleware,
                $name
            );
        }
    }
}
