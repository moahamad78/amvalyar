<?php
declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\CompanyStorageProfile;
use App\Models\User;
use App\Services\AssetPhotoService;
use App\Services\AssetPhotoStorageService;
use App\Services\CompanyStorageProfileService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class AssetPhotoStorageAdapterTest extends TestCase
{
    use DatabaseTransactions;

    private function assetFor(Company $company): Asset
    {
        $category = AssetCategory::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        return Asset::query()->create([
            'company_id' => $company->id,
            'asset_category_id' => $category->id,
            'inventory_code' => 'STOR-' . uniqid(),
            'title' => 'Storage Asset',
            'status' => 'warehouse',
            'custody_type' => 'warehouse',
            'is_active' => true,
        ]);
    }

    public function test_legacy_fallback_stores_on_public_disk(): void
    {
        Storage::fake('public');

        $company = Company::query()->create([
            'name' => 'Fallback Co',
            'code' => 'FB-' . uniqid(),
            'status' => 'active',
        ]);
        $user = User::query()->where('company_id', $company->id)->first();
        if ($user === null) {
            $user = User::factory()->create(['company_id' => $company->id]);
        }
        $asset = $this->assetFor($company);

        app(AssetPhotoService::class)->storeUploadedPhotos(
            $asset,
            [UploadedFile::fake()->image('legacy.jpg')],
            $user
        );

        $photo = $asset->photos()->firstOrFail();
        $this->assertNull($photo->storage_profile_id);
        $this->assertSame('public', $photo->storage_driver);
        $this->assertNull($photo->object_key);
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_company_profile_routes_binary_to_private_company_disk(): void
    {
        Storage::fake('company-byos');

        $company = Company::query()->create([
            'name' => 'BYOS Co',
            'code' => 'BY-' . uniqid(),
            'status' => 'active',
        ]);
        $user = User::query()->where('company_id', $company->id)->first();
        if ($user === null) {
            $user = User::factory()->create(['company_id' => $company->id]);
        }
        $asset = $this->assetFor($company);

        $profile = CompanyStorageProfile::query()->create([
            'company_id' => $company->id,
            'name' => 'Main BYOS',
            'driver' => 's3',
            'bucket' => 'test',
            'is_default' => true,
            'is_active' => true,
        ]);

        $profiles = Mockery::mock(CompanyStorageProfileService::class);
        $profiles->shouldReceive('defaultForCompany')->with($company->id)->andReturn($profile);
        $profiles->shouldReceive('objectKey')
            ->once()
            ->andReturn('companies/'.$company->id.'/assets/'.$asset->id.'/photos/test.jpg');
        $profiles->shouldReceive('disk')->with($profile)->andReturn(Storage::disk('company-byos'));

        $storage = new AssetPhotoStorageService($profiles);
        $service = new AssetPhotoService($storage);

        $service->storeUploadedPhotos(
            $asset,
            [UploadedFile::fake()->image('byos.jpg')],
            $user
        );

        $photo = $asset->photos()->firstOrFail();
        $this->assertSame($profile->id, $photo->storage_profile_id);
        $this->assertSame('s3', $photo->storage_driver);
        $this->assertSame(
            'companies/'.$company->id.'/assets/'.$asset->id.'/photos/test.jpg',
            $photo->object_key
        );
        Storage::disk('company-byos')->assertExists($photo->object_key);
    }

    public function test_delete_uses_same_storage_profile_and_keeps_primary_rotation(): void
    {
        Storage::fake('company-byos');

        $company = Company::query()->create([
            'name' => 'Delete Co',
            'code' => 'DEL-' . uniqid(),
            'status' => 'active',
        ]);
        $user = User::query()->where('company_id', $company->id)->first();
        if ($user === null) {
            $user = User::factory()->create(['company_id' => $company->id]);
        }
        $asset = $this->assetFor($company);

        $profile = CompanyStorageProfile::query()->create([
            'company_id' => $company->id,
            'name' => 'Delete BYOS',
            'driver' => 's3',
            'bucket' => 'test',
            'is_default' => true,
            'is_active' => true,
        ]);

        $profiles = Mockery::mock(CompanyStorageProfileService::class);
        $profiles->shouldReceive('defaultForCompany')->twice()->andReturn($profile);
        $profiles->shouldReceive('objectKey')
            ->twice()
            ->andReturn(
                'companies/'.$company->id.'/assets/'.$asset->id.'/photos/a.jpg',
                'companies/'.$company->id.'/assets/'.$asset->id.'/photos/b.jpg'
            );
        $profiles->shouldReceive('disk')->andReturn(Storage::disk('company-byos'));

        $storage = new AssetPhotoStorageService($profiles);
        $service = new AssetPhotoService($storage);

        $service->storeUploadedPhotos(
            $asset,
            [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
            $user
        );

        $first = $asset->photos()->orderBy('id')->firstOrFail();
        $second = $asset->photos()->orderBy('id')->skip(1)->firstOrFail();
        $this->assertTrue($first->is_primary);

        $service->delete($first);

        Storage::disk('company-byos')->assertMissing((string) $first->object_key);
        $this->assertTrue((bool) $second->fresh()->is_primary);
    }
}