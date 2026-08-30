<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetPhoto;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AssetPhotoStorageService
{
    public function __construct(
        private readonly CompanyStorageProfileService $profiles
    ) {
    }

    /**
     * @return array{storage_profile_id:?int,storage_driver:string,object_key:?string,path:string}
     */
    public function store(Asset $asset, UploadedFile $file): array
    {
        $companyId = (int) $asset->company_id;
        if ($companyId <= 0) {
            throw ValidationException::withMessages([
                'photos' => 'شرکت دارایی برای ذخیره عکس مشخص نیست.',
            ]);
        }

        $profile = $this->profiles->defaultForCompany($companyId);

        if ($profile === null) {
            $path = $file->store(
                'assets/' . $companyId . '/' . $asset->id,
                'public'
            );

            if (!is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    'photos' => 'ذخیره عکس در فضای پیش‌فرض انجام نشد.',
                ]);
            }

            return [
                'storage_profile_id' => null,
                'storage_driver' => 'public',
                'object_key' => null,
                'path' => $path,
            ];
        }

        $extension = strtolower(
            (string) (
                $file->guessExtension()
                ?: $file->getClientOriginalExtension()
                ?: 'bin'
            )
        );

        $key = $this->profiles->objectKey(
            $profile,
            $companyId,
            (int) $asset->id,
            $extension
        );

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages([
                'photos' => 'فایل عکس قابل خواندن نیست.',
            ]);
        }

        try {
            $ok = $this->profiles
                ->disk($profile)
                ->put($key, $stream, [
                    'visibility' => 'private',
                    'ContentType' => $file->getMimeType(),
                ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($ok !== true) {
            throw ValidationException::withMessages([
                'photos' => 'ذخیره عکس در فضای اختصاصی شرکت انجام نشد.',
            ]);
        }

        return [
            'storage_profile_id' => (int) $profile->id,
            'storage_driver' => 's3',
            'object_key' => $key,
            // path retained as a compatibility metadata field.
            'path' => $key,
        ];
    }

    public function deleteBinary(AssetPhoto $photo): void
    {
        if (
            $photo->storage_profile_id !== null
            && $photo->storage_driver === 's3'
            && trim((string) $photo->object_key) !== ''
        ) {
            $profile = $photo->storageProfile()
                ->withoutGlobalScopes()
                ->first();

            if ($profile !== null) {
                $this->profiles
                    ->disk($profile)
                    ->delete((string) $photo->object_key);
            }

            return;
        }

        Storage::disk('public')->delete((string) $photo->path);
    }

    public function url(AssetPhoto $photo, int $minutes = 10): string
    {
        if (
            $photo->storage_profile_id !== null
            && $photo->storage_driver === 's3'
            && trim((string) $photo->object_key) !== ''
        ) {
            $profile = $photo->storageProfile()
                ->withoutGlobalScopes()
                ->first();

            if ($profile === null || (int) $profile->company_id !== (int) $photo->company_id) {
                return '';
            }

            $disk = $this->profiles->disk($profile);

            try {
                return $disk->temporaryUrl(
                    (string) $photo->object_key,
                    now()->addMinutes(max(1, $minutes))
                );
            } catch (Throwable) {
                return '';
            }
        }

        return Storage::disk('public')->url((string) $photo->path);
    }
}