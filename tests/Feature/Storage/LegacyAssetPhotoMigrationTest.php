<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetPhoto;
use App\Models\Company;
use App\Models\CompanyStorageProfile;
use App\Services\CompanyStorageProfileService;
use App\Services\LegacyAssetPhotoMigrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class LegacyAssetPhotoMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private function fixture(): array
    {
        $company=Company::query()->create(['name'=>'Legacy Migration Co','code'=>'LM-'.uniqid(),'status'=>'active']);
        $category=AssetCategory::withoutGlobalScopes()->where('is_active',true)->orderBy('id')->firstOrFail();
        $asset=Asset::query()->create([
            'company_id'=>$company->id,'asset_category_id'=>$category->id,
            'inventory_code'=>'LM-'.uniqid(),'title'=>'Legacy Migration Asset',
            'status'=>'warehouse','custody_type'=>'warehouse','is_active'=>true,
        ]);
        $profile=CompanyStorageProfile::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'name'=>'Target','driver'=>'s3',
            'bucket'=>'test','is_default'=>true,'is_active'=>true,
        ]);
        return [$company,$asset,$profile];
    }

    public function test_migration_copies_then_updates_metadata_and_keeps_source_by_default(): void
    {
        Storage::fake('public');Storage::fake('company-byos');
        [$company,$asset,$profile]=$this->fixture();
        $path='assets/'.$company->id.'/'.$asset->id.'/legacy.jpg';
        Storage::disk('public')->put($path,'legacy-binary');
        $photo=AssetPhoto::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'asset_id'=>$asset->id,'path'=>$path,
            'storage_driver'=>'public','object_key'=>null,'is_primary'=>true,'sort_order'=>10,
        ]);

        $profiles=Mockery::mock(CompanyStorageProfileService::class);
        $profiles->shouldReceive('defaultForCompany')->with($company->id)->andReturn($profile);
        $profiles->shouldReceive('objectKey')->once()->andReturn('companies/'.$company->id.'/assets/'.$asset->id.'/photos/new.jpg');
        $profiles->shouldReceive('disk')->with($profile)->andReturn(Storage::disk('company-byos'));

        $result=(new LegacyAssetPhotoMigrationService($profiles))->migrate($photo);

        $this->assertSame('migrated',$result['status']);
        Storage::disk('public')->assertExists($path);
        Storage::disk('company-byos')->assertExists($result['object_key']);
        $fresh=$photo->fresh();
        $this->assertSame($profile->id,$fresh->storage_profile_id);
        $this->assertSame('s3',$fresh->storage_driver);
        $this->assertSame($result['object_key'],$fresh->object_key);
    }

    public function test_missing_source_does_not_change_metadata(): void
    {
        Storage::fake('public');
        [$company,$asset,$profile]=$this->fixture();
        $photo=AssetPhoto::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'asset_id'=>$asset->id,'path'=>'missing.jpg',
            'storage_driver'=>'public','object_key'=>null,'is_primary'=>true,'sort_order'=>10,
        ]);

        $profiles=Mockery::mock(CompanyStorageProfileService::class);
        $profiles->shouldReceive('defaultForCompany')->with($company->id)->andReturn($profile);

        $result=(new LegacyAssetPhotoMigrationService($profiles))->migrate($photo);
        $this->assertSame('missing',$result['status']);
        $this->assertSame('public',$photo->fresh()->storage_driver);
        $this->assertNull($photo->fresh()->storage_profile_id);
    }

    public function test_dry_run_command_does_not_mutate_photo(): void
    {
        Storage::fake('public');
        [$company,$asset,$profile]=$this->fixture();
        $path='assets/'.$company->id.'/'.$asset->id.'/dry.jpg';
        Storage::disk('public')->put($path,'x');
        $photo=AssetPhoto::withoutGlobalScopes()->create([
            'company_id'=>$company->id,'asset_id'=>$asset->id,'path'=>$path,
            'storage_driver'=>'public','object_key'=>null,'is_primary'=>true,'sort_order'=>10,
        ]);

        $this->artisan('assets:migrate-legacy-photos',['--company'=>$company->id])
            ->assertSuccessful();

        $this->assertSame('public',$photo->fresh()->storage_driver);
        $this->assertNull($photo->fresh()->storage_profile_id);
        Storage::disk('public')->assertExists($path);
    }
}