<?php

declare(strict_types=1);

namespace Tests\Feature\Stocktake;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Site;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use App\Services\PermissionRegistryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StocktakeReconciliationUiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reconciliation_routes_use_dedicated_permission(): void
    {
        foreach ([
            'stocktakes.reconciliation',
            'stocktakes.reconciliation.apply',
            'stocktakes.reconciliation.resolve',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('permission:stocktakes.reconcile', $route->gatherMiddleware(), $name);
        }
    }

    public function test_permission_registry_registers_reconcile_permission(): void
    {
        $names = app(PermissionRegistryService::class)->registeredNames();
        $this->assertContains('stocktakes.reconcile', $names);
    }

    public function test_completed_stocktake_reconciliation_page_shows_three_way_state(): void
    {
        [$company,$user,$stocktake,$item] = $this->fixture();
        $this->grant($user, 'stocktakes.reconcile');

        $this->loginAs($user);

        $this->get(route('stocktakes.reconciliation', $stocktake))
            ->assertOk()
            ->assertSee('Expected /')
            ->assertSee('Observed /')
            ->assertSee('Current /')
            ->assertSee('اعمال اطلاعات مشاهده‌شده')
            ->assertSee('مختومه بدون تغییر اطلاعات اصلی');
    }

    public function test_cross_tenant_stocktake_reconciliation_is_hidden_as_not_found(): void
    {
        [$company,$user,$stocktake] = $this->fixture();
        $other = Company::withoutGlobalScopes()->create(['name'=>'Other '.uniqid(),'code'=>'OTH-'.uniqid(),'is_active'=>true]);
        $outsider = User::withoutGlobalScopes()->create([
            'company_id'=>$other->id,'username'=>'outsider-'.uniqid(),'name'=>'Outsider','email'=>uniqid().'@example.test',
            'password'=>bcrypt('password'),'is_active'=>true,
        ]);
        $this->grant($outsider, 'stocktakes.reconcile');

        $this->loginAs($outsider);

        $this->get(route('stocktakes.reconciliation', $stocktake))
            ->assertNotFound();
    }

    public function test_apply_route_reconciles_completed_misplaced_item(): void
    {
        [$company,$user,$stocktake,$item,$asset] = $this->fixture();
        $this->grant($user, 'stocktakes.reconcile');

        $site=Site::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'name'=>'Observed Site','code'=>'RS-'.uniqid(),
            'type'=>'factory','is_active'=>true,'sort_order'=>10,
        ]);
        $location=Location::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'site_id'=>$site->id,'name'=>'Observed Location',
            'code'=>'RL-'.uniqid(),'type'=>'room','is_active'=>true,'sort_order'=>10,
        ]);
        $item->forceFill([
            'observed_custody_type'=>'organization',
            'observed_site_id'=>$site->id,
            'observed_location_id'=>$location->id,
        ])->save();

        $this->loginAs($user);

        $this->post(route('stocktakes.reconciliation.apply',[$stocktake,$item]),['note'=>'UI verified'])
            ->assertRedirect();

        $this->assertSame(StocktakeItem::RECONCILIATION_APPLIED,$item->fresh()->reconciliation_status);
        $this->assertSame($location->id,$asset->fresh()->current_location_id);
    }

    private function loginAs(User $user): void
    {
        $response=$this->post('/login',[
            'username'=>$user->username,
            'password'=>'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty(session('domain_session_id'));
        $this->assertSame($user->id,(int) session('domain_user_id'));
    }

    private function grant(User $user, string $name): void
    {
        app(PermissionRegistryService::class)->sync();
        $permission=Permission::query()->where('name',$name)->firstOrFail();

        $role=\App\Models\Role::withoutGlobalScopes()->create([
            'company_id'=>$user->company_id,
            'name'=>'role-'.uniqid(),
            'display_name'=>'Test Role',
            'is_system'=>false,
            'is_active'=>true,
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user->forceFill(['role_id'=>$role->id])->save();
        $user->unsetRelation('role');

        $this->assertTrue($user->fresh()->hasPermission($name));
    }

    private function fixture(): array
    {
        $company=Company::withoutGlobalScopes()->create([
            'name'=>'Recon UI '.uniqid(),'code'=>'RUI-'.uniqid(),'is_active'=>true,
        ]);
        $user=User::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'username'=>'recon-'.uniqid(),'name'=>'Recon User','email'=>uniqid().'@example.test',
            'password'=>bcrypt('password'),'is_active'=>true,
        ]);
        $category=AssetCategory::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'name'=>'Category','code'=>'CAT-'.uniqid(),
            'sort_order'=>10,'is_active'=>true,
        ]);
        $asset=Asset::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'asset_category_id'=>$category->id,
            'inventory_code'=>'INV-'.uniqid(),'asset_code'=>'AST-'.uniqid(),
            'title'=>'Reconciliation UI Asset','status'=>'warehouse','custody_type'=>'warehouse','is_active'=>true,
        ]);
        $stocktake=Stocktake::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'code'=>'STK-'.uniqid(),'title'=>'UI Stocktake',
            'scope_type'=>'company','status'=>Stocktake::STATUS_COMPLETED,
            'created_by'=>$user->id,'completed_by'=>$user->id,'completed_at'=>now(),
        ]);
        $item=StocktakeItem::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'stocktake_id'=>$stocktake->id,'asset_id'=>$asset->id,
            'expected_status'=>'warehouse','expected_custody_type'=>'warehouse',
            'result_status'=>StocktakeItem::RESULT_MISPLACED,
            'observed_custody_type'=>'organization',
            'count_round'=>1,'reconciliation_status'=>StocktakeItem::RECONCILIATION_PENDING,
        ]);

        return [$company,$user,$stocktake,$item,$asset];
    }
}