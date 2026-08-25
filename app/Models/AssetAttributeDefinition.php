<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AssetAttributeDefinition extends Model
{
    use BelongsToCompany;

    protected $fillable = [

        'company_id',
        'asset_type_id',

        'name',
        'code',

        'data_type',
        'required_stage',

        'unit',
        'placeholder',
        'help_text',

        'is_active',
        'sort_order',

        'settings',
    ];


    protected $casts = [

        'is_active' =>
            'boolean',

        'sort_order' =>
            'integer',

        'settings' =>
            'array',
    ];


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }


    public function assetType(): BelongsTo
    {
        return $this->belongsTo(
            AssetType::class,
            'asset_type_id'
        );
    }


    public function options(): HasMany
    {
        return $this->hasMany(
            AssetAttributeOption::class,
            'asset_attribute_definition_id'
        )
            ->orderBy('sort_order')
            ->orderBy('id');
    }


    public function values(): HasMany
    {
        return $this->hasMany(
            AssetAttributeValue::class,
            'asset_attribute_definition_id'
        );
    }
}