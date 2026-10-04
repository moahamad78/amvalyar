<?php

namespace Tests\Feature\Permissions;

use App\Models\{Asset, Company, Permission, Role, User};
use App\Services\RoleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RoleVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_employee_cannot_read_other_holders_or_financial_values_even_with_excess_permissions(): void
    {
        $key = Str::random(12);
        $company = Company::create(['name' => $key, 'code' => $key, 'status' => 'active', 'plan' => 'basic', 'max_users' => 100, 'max_assets' => 100]);
        $role = Role::create(['company_id' => $company->id, 'name' => 'employee', 'display_name' => 'پرسنل', 'is_active' => true]);
        $permission = Permission::firstOrCreate(['name' => 'assets.edit'], ['display_name' => 'ویرایش', 'is_active' => true]);
        $role->permissions()->attach($permission);
        foreach (['assets.view', 'reports.export', 'asset_repairs.view', 'asset_repairs.create'] as $name) {
            $extra = Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_active' => true]);
            $role->permissions()->syncWithoutDetaching([$extra->id]);
        }
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => $key, 'username' => $key, 'email' => $key.'@example.test', 'password' => 'test-password', 'is_active' => true]);
        $category = \App\Models\AssetCategory::create(['name' => $key, 'code' => $key, 'is_active' => true]);
        $own = Asset::create(['company_id' => $company->id, 'asset_category_id' => $category->id, 'title' => 'Own', 'inventory_code' => 'own-'.$key, 'status' => 'assigned', 'custody_type' => 'user', 'custody_user_id' => $user->id, 'purchase_price' => 987654]);
        $other = Asset::create(['company_id' => $company->id, 'asset_category_id' => $category->id, 'title' => 'Other', 'inventory_code' => 'other-'.$key, 'status' => 'warehouse', 'purchase_price' => 123456]);
        $this->actingAs($user);
        $this->assertSame('self', app(RoleAccessService::class)->scope($user));
        $this->assertFalse($user->hasPermission('assets.edit'));
        $this->assertFalse($user->hasPermission('reports.export'));
        $this->assertFalse(app(RoleAccessService::class)->financial($user));
        $this->assertNull(Asset::find($other->id));
        $this->assertNull(Asset::findOrFail($own->id)->purchase_price);
        $this->assertSame([$own->id], Asset::query()->pluck('id')->all());
        $this->withoutMiddleware(\App\Http\Middleware\EnsureActiveLoginSession::class);
        $this->get('/dashboard')->assertOk()->assertSee('Own')->assertDontSee('Other')->assertDontSee('987654')->assertDontSee('مصرف ظرفیت شرکت');
        $this->get(route('assets.show', $other))->assertNotFound();
        $this->get(route('assets.show', $own))->assertOk()->assertDontSee('987654');
        $this->get('/asset-repairs/create')->assertOk()->assertSee('Own')->assertDontSee('Other')->assertDontSee('هزینه برآوردی');
        $this->post(route('asset-repairs.store'), [
            'asset_id' => $own->id, 'title' => 'Repair', 'problem_description' => 'Problem',
            'priority' => 'normal', 'estimated_cost' => 1000000,
        ])->assertSessionHasErrors('estimated_cost');
        $this->get('/initial-setup')->assertForbidden();
        $this->get('/organizational-assets')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/workflows')->assertForbidden();
        $this->get('/operational-alerts')->assertForbidden();

        auth()->logout();
        $financialRole = Role::create(['company_id' => $company->id, 'name' => 'finance_manager', 'display_name' => 'مالی', 'is_active' => true]);
        $user->role_id = $financialRole->id;
        $user->save();
        $user->unsetRelation('role');
        $this->actingAs($user);
        $this->assertSame('company', app(RoleAccessService::class)->scope($user));
        $this->assertNotNull(Asset::find($other->id));
        $this->assertEquals(987654, Asset::findOrFail($own->id)->purchase_price);
    }

    public function test_financial_access_is_not_implied_by_operational_roles(): void
    {
        $access = app(RoleAccessService::class);
        foreach (['admin', 'company_admin', 'finance_manager', 'accounting_head', 'ceo'] as $name) {
            $user = new User(['is_super_admin' => false]);
            $user->setRelation('role', new Role(['name' => $name, 'is_active' => true]));
            $this->assertTrue($access->financial($user), $name);
        }
        foreach (['employee', 'unit_manager', 'warehouse_supervisor', 'engineering_head', 'inspection_head', 'chief_asset_keeper'] as $name) {
            $user = new User(['is_super_admin' => false]);
            $user->setRelation('role', new Role(['name' => $name, 'is_active' => false]));
            $this->assertFalse($access->financial($user), $name);
        }
        $inactiveAdmin = new User(['is_super_admin' => false]);
        $inactiveAdmin->setRelation('role', new Role(['name' => 'company_admin', 'is_active' => false]));
        $this->assertFalse($access->financial($inactiveAdmin));
        $this->assertSame('self', $access->scope($inactiveAdmin));
    }

    public function test_unit_manager_only_sees_own_department_and_cannot_infer_prices_from_filters(): void
    {
        $key = Str::random(12);
        $company = Company::create(['name' => $key, 'code' => $key, 'status' => 'active', 'plan' => 'basic', 'max_users' => 100, 'max_assets' => 100]);
        $role = Role::create(['company_id' => $company->id, 'name' => 'unit_manager', 'display_name' => 'مدیر واحد', 'is_active' => true]);
        $user = User::create(['company_id' => $company->id, 'role_id' => $role->id, 'name' => $key, 'username' => $key, 'email' => $key.'@example.test', 'password' => 'test-password', 'is_active' => true]);
        $department = \App\Models\Department::create(['company_id' => $company->id, 'name' => $key, 'code' => $key, 'is_active' => true]);
        \App\Models\Employee::create(['company_id' => $company->id, 'user_id' => $user->id, 'department_id' => $department->id, 'personnel_code' => $key, 'first_name' => 'Unit', 'last_name' => 'Manager', 'display_name' => 'Unit manager', 'is_active' => true]);
        $category = \App\Models\AssetCategory::create(['name' => $key, 'code' => $key, 'is_active' => true]);
        $inside = Asset::create(['company_id' => $company->id, 'asset_category_id' => $category->id, 'title' => 'UnitAsset', 'inventory_code' => 'inside-'.$key, 'status' => 'assigned', 'custody_type' => 'organization', 'custody_department_id' => $department->id]);
        Asset::create(['company_id' => $company->id, 'asset_category_id' => $category->id, 'title' => 'OutsideAsset', 'inventory_code' => 'outside-'.$key, 'status' => 'warehouse']);
        $this->actingAs($user);
        $this->assertSame('department', app(RoleAccessService::class)->scope($user));
        $this->assertSame([$inside->id], Asset::query()->pluck('id')->all());
        $request = \Illuminate\Http\Request::create('/reports', 'GET', ['min_value' => 100]);
        $request->setUserResolver(fn () => $user);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\AssetReportFilters::class)->validate($request);
    }

    public function test_custom_operational_role_requires_explicit_company_wide_permission(): void
    {
        $key = Str::random(12);
        $company = Company::create(['name' => $key, 'code' => $key, 'status' => 'active']);
        $role = Role::create(['company_id' => $company->id, 'name' => 'custom_operator_'.$key, 'display_name' => 'اپراتور سفارشی', 'is_active' => true]);
        $edit = Permission::firstOrCreate(['name' => 'assets.edit'], ['display_name' => 'ویرایش', 'is_active' => true]);
        $viewAll = Permission::firstOrCreate(['name' => 'assets.view_all'], ['display_name' => 'مشاهده همه', 'is_active' => true]);
        $role->permissions()->sync([$edit->id]);
        $user = User::create([
            'company_id' => $company->id, 'role_id' => $role->id, 'name' => $key,
            'username' => 'custom-'.$key, 'email' => $key.'@custom.test', 'password' => 'test-password', 'is_active' => true,
        ]);
        $access = app(RoleAccessService::class);
        $this->assertSame('self', $access->scope($user));
        $role->permissions()->syncWithoutDetaching([$viewAll->id]);
        $user->unsetRelation('role');
        $this->assertSame('company', $access->scope($user));
    }
}
