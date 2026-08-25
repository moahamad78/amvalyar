<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AssetType extends Model
{
    use BelongsToCompany;

    protected $fillable = [

        'company_id',
        'asset_category_id',

        'name',
        'code',
        'coding_code',

        'description',

        'is_active',
        'sort_order',
    ];


    protected $casts = [

        'is_active' =>
            'boolean',

        'sort_order' =>
            'integer',
    ];


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }


    public function category(): BelongsTo
    {
        return $this->belongsTo(
            AssetCategory::class,
            'asset_category_id'
        );
    }


    public function assets(): HasMany
    {
        return $this->hasMany(
            Asset::class,
            'asset_type_id'
        );
    }


    public function requestItems(): HasMany
    {
        return $this->hasMany(
            InventoryRequestItem::class,
            'asset_type_id'
        );
    }


    public function attributeDefinitions(): HasMany
    {
        return $this->hasMany(
            AssetAttributeDefinition::class,
            'asset_type_id'
        )
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}