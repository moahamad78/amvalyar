<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetPhoto;
use App\Models\CompanyStorageProfile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class LegacyAssetPhotoMigrationService
{
    public function __construct(
        private readonly CompanyStorageProfileService $profiles
    ) {
    }

    /**
     * @return array{status:string,photo_id:int,object_key:?string,message:?string}
     */
    public function migrate(AssetPhoto $photo, bool $deleteSource = false): array
    {
        if (
            $photo->storage_driver !== 'public'
            || $photo->storage_profile_id !== null
            || trim((string) $photo->path) === ''
        ) {
            return ['status'=>'skipped','photo_id'=>(int)$photo->id,'object_key'=>null,'message'=>'not_legacy'];
        }

        $companyId=(int)$photo->company_id;
        $profile=$this->profiles->defaultForCompany($companyId);

        if ($profile === null) {
            return ['status'=>'skipped','photo_id'=>(int)$photo->id,'object_key'=>null,'message'=>'no_active_profile'];
        }

        if ((int)$profile->company_id !== $companyId) {
            throw new RuntimeException('Storage profile tenant mismatch.');
        }

        $source=Storage::disk('public');

        if (!$source->exists((string)$photo->path)) {
            return ['status'=>'missing','photo_id'=>(int)$photo->id,'object_key'=>null,'message'=>'source_missing'];
        }

        $extension=strtolower((string)pathinfo((string)$photo->path,PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension='bin';
        }

        $key=$this->profiles->objectKey(
            $profile,
            $companyId,
            (int)$photo->asset_id,
            $extension
        );

        $read=$source->readStream((string)$photo->path);
        if (!is_resource($read)) {
            throw new RuntimeException('Could not open legacy source stream.');
        }

        try {
            $target=$this->profiles->disk($profile);
            $ok=$target->put($key,$read,[
                'visibility'=>'private',
                'ContentType'=>$photo->mime_type ?: null,
            ]);
        } finally {
            if (is_resource($read)) {
                fclose($read);
            }
        }

        if ($ok !== true || !$target->exists($key)) {
            try {$target->delete($key);} catch (Throwable) {}
            throw new RuntimeException('Target write verification failed.');
        }

        try {
            $photo->forceFill([
                'storage_profile_id'=>$profile->id,
                'storage_driver'=>'s3',
                'object_key'=>$key,
                'path'=>$key,
            ])->save();
        } catch (Throwable $e) {
            try {$target->delete($key);} catch (Throwable $cleanup) {report($cleanup);}
            throw $e;
        }

        if ($deleteSource) {
            try {
                $source->delete((string)$photo->getOriginal('path'));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return ['status'=>'migrated','photo_id'=>(int)$photo->id,'object_key'=>$key,'message'=>null];
    }
}