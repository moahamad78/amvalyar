<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetAttributeValue extends Model
{
    use BelongsToCompany;

    protected $fillable = [

        'company_id',
        'asset_id',

        'asset_attribute_definition_id',

        'value_text',
        'value_number',
        'value_date',
        'value_boolean',

        'option_id',

        'file_path',
        'original_name',
        'mime_type',
        'file_size',

        'updated_by_user_id',
    ];


    protected $casts = [

        'value_number' =>
            'decimal:6',

        'value_date' =>
            'date',

        'value_boolean' =>
            'boolean',

        'file_size' =>
            'integer',
    ];


    public function asset(): BelongsTo
    {
        return $this->belongsTo(
            Asset::class
        );
    }


    public function definition(): BelongsTo
    {
        return $this->belongsTo(
            AssetAttributeDefinition::class,
            'asset_attribute_definition_id'
        );
    }


    public function option(): BelongsTo
    {
        return $this->belongsTo(
            AssetAttributeOption::class,
            'option_id'
        );
    }


    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by_user_id'
        );
    }
}