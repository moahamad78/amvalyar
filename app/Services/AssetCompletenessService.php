<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAttributeDefinition;
use App\Models\AssetAttributeValue;

final class AssetCompletenessService
{
    public const CONTEXT_WAREHOUSE_ENTRY =
        'warehouse_entry';

    public const CONTEXT_ASSET_MANAGER_REVIEW =
        'asset_manager_review';

    public const CONTEXT_BEFORE_DELIVERY =
        'before_delivery';


    public function check(
        Asset $asset,
        string $context = self::CONTEXT_WAREHOUSE_ENTRY
    ): array {

        $this->ensureValidContext(
            $context
        );


        $asset->loadMissing([
            'category',
            'assetType.attributeDefinitions.options',
            'attributeValues.definition',
            'attributeValues.option',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Core Identity
        |--------------------------------------------------------------------------
        |
        | این سه مورد بخشی از هویت اصلی Asset هستند و Dynamic نیستند.
        |
        */

        $checks = [

            'category' => [

                'label' =>
                    'دسته‌بندی',

                'required' =>
                    true,

                'complete' =>
                    $asset->asset_category_id
                    !==
                    null,

                'source' =>
                    'core',
            ],


            'asset_type' => [

                'label' =>
                    'نوع دارایی',

                'required' =>
                    true,

                'complete' =>
                    $asset->asset_type_id
                    !==
                    null,

                'source' =>
                    'core',
            ],


            'title' => [

                'label' =>
                    'عنوان دارایی',

                'required' =>
                    true,

                'complete' =>
                    $this->hasText(
                        $asset->title
                    ),

                'source' =>
                    'core',
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Dynamic Requirements
        |--------------------------------------------------------------------------
        |
        | required_stage:
        |
        | optional
        | warehouse_entry
        | asset_manager_review
        | before_delivery
        |
        | قانون مرحله‌ای تجمعی است:
        |
        | اگر چیزی در warehouse_entry لازم باشد،
        | در مراحل بعدی هم باید کامل باقی بماند.
        |
        */

        if (
            $asset->assetType
            !==
            null
        ) {

            $definitions =
                $asset->assetType
                    ->attributeDefinitions
                    ->filter(
                        fn (
                            AssetAttributeDefinition $definition
                        ): bool =>
                            $definition->is_active
                            ===
                            true
                    )
                    ->sortBy([
                        ['sort_order', 'asc'],
                        ['id', 'asc'],
                    ]);


            $values =
                $asset->attributeValues
                    ->keyBy(
                        'asset_attribute_definition_id'
                    );


            foreach (
                $definitions
                as
                $definition
            ) {

                $required =
                    $this->isRequiredAtContext(
                        $definition->required_stage,
                        $context
                    );


                /*
                 * Optional Attributeها برای نمایش وضعیت داخل checks
                 * باقی می‌مانند ولی روی Complete کلی اثر ندارند.
                 */

                $value =
                    $values->get(
                        $definition->id
                    );


                $checks[
                    'attribute_'
                    . $definition->id
                ] = [

                    'label' =>
                        $definition->name,

                    'required' =>
                        $required,

                    'complete' =>
                        $this->attributeValueIsComplete(
                            $definition,
                            $value
                        ),

                    'source' =>
                        'dynamic',

                    'definition_id' =>
                        $definition->id,

                    'code' =>
                        $definition->code,

                    'data_type' =>
                        $definition->data_type,

                    'required_stage' =>
                        $definition->required_stage,
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Permanent Code + Physical Plate
        |--------------------------------------------------------------------------
        |
        | permanent_asset_code_is_physical_plate_v3
        |
        | Asset Manager Review:
        |   asset_code and plate_number are NOT prerequisites.
        |
        | Before Delivery:
        |   the permanent asset_code is the physical plate identifier and is required.
        |
        */

        if (
            $context
            ===
            self::CONTEXT_BEFORE_DELIVERY
        ) {

            $checks['asset_code'] = [

                'label' =>
                    'کد دائمی اموال',

                'required' =>
                    true,

                'complete' =>
                    $this->hasText(
                        $asset->asset_code
                    ),

                'source' =>
                    'core',
            ];
        }

        $requiredChecks =
            collect($checks)
                ->filter(
                    fn (
                        array $check
                    ): bool =>
                        $check['required']
                        ===
                        true
                );


        $missing =
            $requiredChecks
                ->filter(
                    fn (
                        array $check
                    ): bool =>
                        $check['complete']
                        !==
                        true
                )
                ->map(
                    fn (
                        array $check
                    ): string =>
                        $check['label']
                )
                ->values()
                ->all();


        $completedCount =
            $requiredChecks
                ->filter(
                    fn (
                        array $check
                    ): bool =>
                        $check['complete']
                        ===
                        true
                )
                ->count();


        $totalCount =
            $requiredChecks
                ->count();


        return [

            'context' =>
                $context,

            'complete' =>
                $missing === [],

            'checks' =>
                $checks,

            'missing' =>
                $missing,

            'completed_count' =>
                $completedCount,

            'total_count' =>
                $totalCount,

            'percentage' =>
                $totalCount > 0
                    ? (int) round(
                        (
                            $completedCount
                            /
                            $totalCount
                        )
                        *
                        100
                    )
                    : 100,
        ];
    }


    public function isComplete(
        Asset $asset,
        string $context = self::CONTEXT_WAREHOUSE_ENTRY
    ): bool {

        return $this->check(
            $asset,
            $context
        )['complete'];
    }


    public function missing(
        Asset $asset,
        string $context = self::CONTEXT_WAREHOUSE_ENTRY
    ): array {

        return $this->check(
            $asset,
            $context
        )['missing'];
    }


    private function isRequiredAtContext(
        string $requiredStage,
        string $context
    ): bool {

        if (
            $requiredStage
            ===
            'optional'
        ) {

            return false;
        }


        $ranks = [

            self::CONTEXT_WAREHOUSE_ENTRY =>
                10,

            self::CONTEXT_ASSET_MANAGER_REVIEW =>
                20,

            self::CONTEXT_BEFORE_DELIVERY =>
                30,
        ];


        $requiredRank =
            $ranks[$requiredStage]
            ??
            PHP_INT_MAX;


        $contextRank =
            $ranks[$context]
            ??
            0;


        return
            $requiredRank
            <=
            $contextRank;
    }


    private function attributeValueIsComplete(
        AssetAttributeDefinition $definition,
        ?AssetAttributeValue $value
    ): bool {

        if (
            $value
            ===
            null
        ) {

            return false;
        }


        return match (
            $definition->data_type
        ) {

            'text',
            'textarea' =>
                $this->hasText(
                    $value->value_text
                ),


            'number' =>
                $value->value_number
                !==
                null,


            'date' =>
                $value->value_date
                !==
                null,


            /*
             * false هم یک مقدار معتبر برای Boolean است.
             */
            'boolean' =>
                $value->value_boolean
                !==
                null,


            'select' =>
                $value->option_id
                !==
                null,


            'photo' =>
                $this->hasText(
                    $value->file_path
                ),


            default =>
                $this->hasText(
                    $value->value_text
                ),
        };
    }


    private function ensureValidContext(
        string $context
    ): void {

        if (
            !in_array(
                $context,
                [
                    self::CONTEXT_WAREHOUSE_ENTRY,
                    self::CONTEXT_ASSET_MANAGER_REVIEW,
                    self::CONTEXT_BEFORE_DELIVERY,
                ],
                true
            )
        ) {

            throw new \InvalidArgumentException(
                'Asset completeness context is invalid: '
                . $context
            );
        }
    }


    private function hasText(
        mixed $value
    ): bool {

        return
            $value !== null
            &&
            trim(
                (string) $value
            ) !== '';
    }
}