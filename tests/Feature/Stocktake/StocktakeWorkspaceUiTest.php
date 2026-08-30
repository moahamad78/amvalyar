<?php
declare(strict_types=1);

namespace Tests\Feature\Stocktake;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StocktakeWorkspaceUiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stocktake_workspace_routes_are_registered_with_permissions(): void
    {
        $expected=[
            'stocktakes.index'=>'stocktakes.view',
            'stocktakes.create'=>'stocktakes.create',
            'stocktakes.store'=>'stocktakes.create',
            'stocktakes.show'=>'stocktakes.view',
            'stocktakes.start'=>'stocktakes.start',
            'stocktakes.observe'=>'stocktakes.count',
            'stocktakes.missing'=>'stocktakes.count',
            'stocktakes.recount'=>'stocktakes.finalize',
            'stocktakes.complete'=>'stocktakes.finalize',
        ];
        foreach($expected as $name=>$permission){
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route,$name);
            $this->assertContains('permission:'.$permission,$route->gatherMiddleware(),$name);
        }
    }

    public function test_stocktake_workspace_views_exist(): void
    {
        $this->assertTrue(view()->exists('stocktakes.index'));
        $this->assertTrue(view()->exists('stocktakes.create'));
        $this->assertTrue(view()->exists('stocktakes.show'));
    }
}