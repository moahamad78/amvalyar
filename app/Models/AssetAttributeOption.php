<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetAttributeOption extends Model
{
    protected $fillable = [

        'asset_attribute_definition_id',

        'label',
        'value',

        'is_active',
        'sort_order',
    ];


    protected $casts = [

        'is_active' =>
            'boolean',

        'sort_order' =>
            'integer',
    ];


    public function definition(): BelongsTo
    {
        return $this->belongsTo(
            AssetAttributeDefinition::class,
            'asset_attribute_definition_id'
        );
    }
}