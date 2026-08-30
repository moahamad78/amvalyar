<?php
declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetPhoto;
use App\Models\Company;
use App\Models\CompanyStorageProfile;
use App\Models\User;
use App\Services\AssetPhotoService;
use App\Services\AssetPhotoStorageService;
use App\Services\CompanyStorageProfileService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class AssetPhotoStorageHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private function assetFor(Company $company): Asset
    {
        $category=AssetCategory::withoutGlobalScopes()
            ->where('is_active',true)->orderBy('id')->firstOrFail();

        return Asset::query()->create([
            'company_id'=>$company->id,
            'asset_category_id'=>$category->id,
            'inventory_code'=>'HARD-'.uniqid(),
            'title'=>'Hardening Asset',
            'status'=>'warehouse',
            'custody_type'=>'warehouse',
            'is_active'=>true,
        ]);
    }

    public function test_cross_tenant_profile_cannot_delete_binary(): void
    {
        Storage::fake('company-byos');

        $a=Company::query()->create(['name'=>'A','code'=>'HA-'.uniqid(),'status'=>'active']);
        $b=Company::query()->create(['name'=>'B','code'=>'HB-'.uniqid(),'status'=>'active']);

        $asset=$this->assetFor($a);
        $profile=CompanyStorageProfile::withoutGlobalScopes()->create([
            'company_id'=>$b->id,'name'=>'Foreign','driver'=>'s3',
            'bucket'=>'x','is_active'=>true,'is_default'=>true,
        ]);

        $key='companies/'.$a->id.'/assets/'.$asset->id.'/photos/x.jpg';
        Storage::disk('company-byos')->put($key,'x');

        $photo=AssetPhoto::withoutGlobalScopes()->create([
            'company_id'=>$a->id,'asset_id'=>$asset->id,
            'storage_profile_id'=>$profile->id,'storage_driver'=>'s3',
            'object_key'=>$key,'path'=>$key,'is_primary'=>true,'sort_order'=>10,
        ]);

        $profiles=Mockery::mock(CompanyStorageProfileService::class);
        $profiles->shouldNotReceive('disk');

        $service=new AssetPhotoStorageService($profiles);

        try {
            $service->deleteBinary($photo);
            $this->fail('Expected tenant ownership validation failure.');
        } catch (ValidationException) {
            Storage::disk('company-byos')->assertExists($key);
        }
    }

    public function test_metadata_failure_compensates_public_binary(): void
    {
        Storage::fake('public');

        $company=Company::query()->create([
            'name'=>'Compensation Co','code'=>'HC-'.uniqid(),'status'=>'active'
        ]);
        $asset=$this->assetFor($company);

        $user=new User();
        $user->id=PHP_INT_MAX;

        $service=app(AssetPhotoService::class);

        try {
            $service->storeUploadedPhotos(
                $asset,
                [UploadedFile::fake()->image('compensate.jpg')],
                $user
            );
            $this->fail('Expected metadata persistence failure.');
        } catch (\Throwable) {
            $this->assertSame(
                [],
                Storage::disk('public')->allFiles('assets/'.$company->id.'/'.$asset->id)
            );
            $this->assertSame(0,$asset->photos()->count());
        }
    }

    public function test_primary_rotation_is_scoped_to_same_company(): void
    {
        Storage::fake('public');

        $company=Company::query()->create([
            'name'=>'Primary Co','code'=>'HP-'.uniqid(),'status'=>'active'
        ]);

        $user=User::query()->where('company_id',$company->id)->first()
            ?? User::factory()->create(['company_id'=>$company->id]);

        $asset=$this->assetFor($company);
        app(AssetPhotoService::class)->storeUploadedPhotos(
            $asset,
            [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
            $user
        );

        $first=$asset->photos()->orderBy('id')->firstOrFail();
        $second=$asset->photos()->orderBy('id')->skip(1)->firstOrFail();

        app(AssetPhotoService::class)->delete($first);

        $this->assertTrue((bool)$second->fresh()->is_primary);
    }
}