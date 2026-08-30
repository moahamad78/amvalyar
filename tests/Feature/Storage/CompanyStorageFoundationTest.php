<?php
declare(strict_types=1);
namespace Tests\Feature\Storage;
use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\Company;
use App\Models\CompanyStorageProfile;
use App\Services\CompanyStorageProfileService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;
final class CompanyStorageFoundationTest extends TestCase
{
    use DatabaseTransactions;
    public function test_credentials_are_encrypted_and_profile_is_tenant_owned(): void
    {
        $company=Company::query()->create(['name'=>'Storage Test','code'=>'ST-'.uniqid(),'status'=>'active']);
        $profile=CompanyStorageProfile::query()->create([
            'company_id'=>$company->id,'name'=>'Main S3','driver'=>'s3','bucket'=>'private-assets',
            'access_key'=>'ACCESS-SECRET','secret_key'=>'TOP-SECRET','is_default'=>true,'is_active'=>true,
        ]);
        $raw=(string)$profile->getRawOriginal('secret_key');
        $this->assertNotSame('TOP-SECRET',$raw);
        $this->assertSame('TOP-SECRET',$profile->fresh()->secret_key);
        $this->assertSame($company->id,$profile->company_id);
    }
    public function test_object_keys_are_private_logical_keys_and_unique(): void
    {
        $company=Company::query()->create(['name'=>'Storage Test 2','code'=>'ST2-'.uniqid(),'status'=>'active']);
        $profile=CompanyStorageProfile::query()->create(['company_id'=>$company->id,'name'=>'S3','driver'=>'s3','bucket'=>'b','root_prefix'=>'tenant-root','is_default'=>true,'is_active'=>true]);
        $svc=app(CompanyStorageProfileService::class);
        $a=$svc->objectKey($profile,$company->id,77,'JPG');
        $b=$svc->objectKey($profile,$company->id,77,'jpg');
        $this->assertStringStartsWith('tenant-root/companies/'.$company->id.'/assets/77/photos/',$a);
        $this->assertStringEndsWith('.jpg',$a);
        $this->assertNotSame($a,$b);
        $this->assertStringNotContainsString('\\',$a);
    }
    public function test_legacy_asset_photo_metadata_remains_compatible(): void
    {
        $company=Company::query()->create(['name'=>'Legacy Photo Co','code'=>'LP-'.uniqid(),'status'=>'active']);
        $category=\App\Models\AssetCategory::withoutGlobalScopes()
            ->where('is_active',true)->orderBy('id')->firstOrFail();
        $asset=Asset::query()->create([
            'company_id'=>$company->id,
            'asset_category_id'=>$category->id,
            'inventory_code'=>'LEGACY-'.uniqid(),
            'title'=>'Legacy',
            'status'=>'warehouse',
            'custody_type'=>'warehouse',
            'is_active'=>true,
        ]);
        $photo=AssetPhoto::query()->create(['company_id'=>$company->id,'asset_id'=>$asset->id,'path'=>'assets/legacy.jpg','storage_driver'=>'public','object_key'=>null,'is_primary'=>true,'sort_order'=>10]);
        $this->assertNull($photo->storage_profile_id);
        $this->assertSame('public',$photo->storage_driver);
        $this->assertSame('assets/legacy.jpg',$photo->path);
    }
}