<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class AssetPhotoService
{
    /**
     * @param array<int, UploadedFile> $files
     */
    public function storeUploadedPhotos(
        Asset $asset,
        array $files,
        User $user
    ): void {

        if ($files === []) {
            return;
        }


        $hasPrimary =
            $asset->photos()
                ->where(
                    'is_primary',
                    true
                )
                ->exists();


        $nextSortOrder =
            ((int) $asset->photos()
                ->max('sort_order'))
            + 10;


        foreach ($files as $file) {

            if (
                !$file instanceof UploadedFile
                ||
                !$file->isValid()
            ) {
                continue;
            }


            $path =
                $file->store(
                    'assets/' . $asset->company_id . '/' . $asset->id,
                    'public'
                );


            $isPrimary =
                !$hasPrimary;


            AssetPhoto::query()
                ->create([

                    'company_id' =>
                        $asset->company_id,

                    'asset_id' =>
                        $asset->id,

                    'path' =>
                        $path,

                    'original_name' =>
                        $file->getClientOriginalName(),

                    'mime_type' =>
                        $file->getMimeType(),

                    'file_size' =>
                        $file->getSize(),

                    'photo_type' =>
                        'general',

                    'is_primary' =>
                        $isPrimary,

                    'sort_order' =>
                        $nextSortOrder,

                    'uploaded_by_user_id' =>
                        $user->id,
                ]);


            if ($isPrimary) {
                $hasPrimary =
                    true;
            }


            $nextSortOrder +=
                10;
        }
    }


    public function delete(
        AssetPhoto $photo
    ): void {

        Storage::disk('public')
            ->delete(
                $photo->path
            );


        $assetId =
            $photo->asset_id;

        $wasPrimary =
            $photo->is_primary;


        $photo->delete();


        if ($wasPrimary) {

            $next =
                AssetPhoto::query()
                    ->where(
                        'asset_id',
                        $assetId
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();


            if ($next !== null) {

                $next->is_primary =
                    true;

                $next->save();
            }
        }
    }
}