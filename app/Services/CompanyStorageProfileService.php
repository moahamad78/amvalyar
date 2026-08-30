<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\CompanyStorageProfile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
final class CompanyStorageProfileService
{
    public function disk(CompanyStorageProfile $profile): FilesystemAdapter
    {
        if (!$profile->is_active || $profile->driver !== CompanyStorageProfile::DRIVER_S3) {
            throw ValidationException::withMessages(['storage_profile'=>'پروفایل ذخیره‌سازی فعال و قابل استفاده نیست.']);
        }
        return Storage::build([
            'driver'=>'s3',
            'key'=>$profile->access_key,
            'secret'=>$profile->secret_key,
            'region'=>$profile->region ?: 'us-east-1',
            'bucket'=>$profile->bucket,
            'url'=>$profile->url,
            'endpoint'=>$profile->endpoint,
            'use_path_style_endpoint'=>(bool)$profile->use_path_style_endpoint,
            'throw'=>true,
        ]);
    }
    public function defaultForCompany(int $companyId): ?CompanyStorageProfile
    {
        return CompanyStorageProfile::withoutGlobalScopes()
            ->where('company_id',$companyId)->where('is_active',true)
            ->orderByDesc('is_default')->orderBy('id')->first();
    }
    public function objectKey(CompanyStorageProfile $profile,int $companyId,int $assetId,string $extension): string
    {
        $prefix=trim((string)$profile->root_prefix,'/');
        $key='companies/'.$companyId.'/assets/'.$assetId.'/photos/'.\Ramsey\Uuid\Uuid::uuid4()->toString().'.'.strtolower($extension ?: 'bin');
        return $prefix !== '' ? $prefix.'/'.$key : $key;
    }
}