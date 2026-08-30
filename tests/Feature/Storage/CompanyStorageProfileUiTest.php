<?php
declare(strict_types=1);
namespace Tests\Feature\Storage;
use App\Models\Company;
use App\Models\CompanyStorageProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CompanyStorageProfileUiTest extends TestCase
{
 use DatabaseTransactions;
 public function test_routes_are_permission_protected(): void
 {
   $routes=collect(app('router')->getRoutes()->getRoutes())->keyBy(fn($r)=>$r->getName());
   foreach(['company-storage-profiles.index','company-storage-profiles.store','company-storage-profiles.update','company-storage-profiles.test','company-storage-profiles.default'] as $name){
     $this->assertTrue($routes->has($name));$this->assertContains('permission:company_storage.manage',$routes[$name]->gatherMiddleware());
   }
 }
 public function test_profile_secrets_are_not_rendered_in_edit_form(): void
 {
   $company=Company::query()->firstOrFail();
   $user=User::query()->where('company_id',$company->id)->first();
   if(!$user){$this->markTestSkipped('No tenant user fixture available.');}
   $profile=CompanyStorageProfile::withoutGlobalScopes()->create(['company_id'=>$company->id,'name'=>'Private','driver'=>'s3','bucket'=>'b','access_key'=>'ACCESS-SHOULD-NOT-LEAK','secret_key'=>'SECRET-SHOULD-NOT-LEAK','is_active'=>true]);
   $this->actingAs($user);
   if(!$user->isSuperAdmin()&&!$user->hasPermission('company_storage.manage')){$this->markTestSkipped('Fixture user lacks storage permission.');}
   $this->get(route('company-storage-profiles.edit',$profile))->assertOk()->assertDontSee('ACCESS-SHOULD-NOT-LEAK')->assertDontSee('SECRET-SHOULD-NOT-LEAK');
 }
 public function test_cross_tenant_profile_is_not_resolved_by_controller(): void
 {
   $companies=Company::query()->limit(2)->get();if($companies->count()<2){$this->markTestSkipped('Need two companies.');}
   $user=User::query()->where('company_id',$companies[0]->id)->first();if(!$user){$this->markTestSkipped('No tenant user.');}
   $foreign=CompanyStorageProfile::withoutGlobalScopes()->create(['company_id'=>$companies[1]->id,'name'=>'Foreign','driver'=>'s3','bucket'=>'b','is_active'=>true]);
   $this->actingAs($user);
   if(!$user->hasPermission('company_storage.manage')){$this->markTestSkipped('Fixture user lacks permission.');}
   $this->get(route('company-storage-profiles.edit',$foreign))->assertNotFound();
 }
}