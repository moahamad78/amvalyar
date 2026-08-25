<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAttributeDefinition;
use App\Models\AssetAttributeValue;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class AssetAttributeValueService
{
    public function sync(
        Asset $asset,
        Request $request,
        User $user
    ): void {

        $definitions =
            AssetAttributeDefinition::query()
                ->where(
                    'company_id',
                    $asset->company_id
                )
                ->where(
                    'asset_type_id',
                    $asset->asset_type_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->with([
                    'options' => function ($query) {
                        $query->where(
                            'is_active',
                            true
                        );
                    },
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();


        $submitted =
            (array) $request->input(
                'dynamic_attributes',
                []
            );


        foreach ($definitions as $definition) {

            $definitionId =
                (int) $definition->id;


            $existing =
                AssetAttributeValue::query()
                    ->where(
                        'asset_id',
                        $asset->id
                    )
                    ->where(
                        'asset_attribute_definition_id',
                        $definitionId
                    )
                    ->first();


            if (
                $definition->data_type
                ===
                'photo'
            ) {

                $file =
                    $request->file(
                        'dynamic_attribute_files.'
                        . $definitionId
                    );


                if ($file === null) {
                    continue;
                }


                if (
                    $existing?->file_path
                ) {

                    Storage::disk('public')
                        ->delete(
                            $existing->file_path
                        );
                }


                $path =
                    $file->store(
                        'asset-attributes/'
                        . $asset->company_id
                        . '/'
                        . $asset->id,
                        'public'
                    );


                AssetAttributeValue::query()
                    ->updateOrCreate(
                        [
                            'asset_id' =>
                                $asset->id,

                            'asset_attribute_definition_id' =>
                                $definitionId,
                        ],
                        [
                            'company_id' =>
                                $asset->company_id,

                            'value_text' =>
                                null,

                            'value_number' =>
                                null,

                            'value_date' =>
                                null,

                            'value_boolean' =>
                                null,

                            'option_id' =>
                                null,

                            'file_path' =>
                                $path,

                            'original_name' =>
                                $file->getClientOriginalName(),

                            'mime_type' =>
                                $file->getMimeType(),

                            'file_size' =>
                                $file->getSize(),

                            'updated_by_user_id' =>
                                $user->id,
                        ]
                    );


                continue;
            }


            $raw =
                $submitted[$definitionId]
                ?? null;


            $normalizedText =
                is_string($raw)
                    ? trim($raw)
                    : $raw;


            $hasValue =
                !(
                    $normalizedText === null
                    ||
                    $normalizedText === ''
                );


            if (!$hasValue) {

                if ($existing !== null) {

                    $existing->update([

                        'value_text' =>
                            null,

                        'value_number' =>
                            null,

                        'value_date' =>
                            null,

                        'value_boolean' =>
                            null,

                        'option_id' =>
                            null,

                        'updated_by_user_id' =>
                            $user->id,
                    ]);
                }

                continue;
            }


            $values = [

                'company_id' =>
                    $asset->company_id,

                'value_text' =>
                    null,

                'value_number' =>
                    null,

                'value_date' =>
                    null,

                'value_boolean' =>
                    null,

                'option_id' =>
                    null,

                'file_path' =>
                    $existing?->file_path,

                'original_name' =>
                    $existing?->original_name,

                'mime_type' =>
                    $existing?->mime_type,

                'file_size' =>
                    $existing?->file_size,

                'updated_by_user_id' =>
                    $user->id,
            ];


            switch (
                $definition->data_type
            ) {

                case 'number':

                    $values['value_number'] =
                        $raw;

                    break;


                case 'date':

                    $values['value_date'] =
                        $raw;

                    break;


                case 'boolean':

                    $values['value_boolean'] =
                        filter_var(
                            $raw,
                            FILTER_VALIDATE_BOOLEAN,
                            FILTER_NULL_ON_FAILURE
                        );

                    break;


                case 'select':

                    $values['option_id'] =
                        (int) $raw;

                    break;


                case 'textarea':
                case 'text':
                default:

                    $values['value_text'] =
                        (string) $raw;

                    break;
            }


            AssetAttributeValue::query()
                ->updateOrCreate(
                    [
                        'asset_id' =>
                            $asset->id,

                        'asset_attribute_definition_id' =>
                            $definitionId,
                    ],
                    $values
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Remove values belonging to old Asset Type
        |--------------------------------------------------------------------------
        |
        | اگر هنگام Edit نوع دارایی تغییر کرده باشد، مقادیر Definitionهایی که
        | دیگر متعلق به Type جدید نیستند حذف می‌شوند.
        |
        */

        $validDefinitionIds =
            $definitions
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->all();


        $obsolete =
            AssetAttributeValue::query()
                ->where(
                    'asset_id',
                    $asset->id
                )
                ->when(
                    $validDefinitionIds !== [],
                    fn ($query) =>
                        $query->whereNotIn(
                            'asset_attribute_definition_id',
                            $validDefinitionIds
                        )
                )
                ->when(
                    $validDefinitionIds === [],
                    fn ($query) =>
                        $query
                )
                ->get();


        foreach ($obsolete as $value) {

            if ($value->file_path) {

                Storage::disk('public')
                    ->delete(
                        $value->file_path
                    );
            }


            $value->delete();
        }
    }
}