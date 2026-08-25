<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetCategoryCodingMapping extends Model
{
    use BelongsToCompany;


    protected $fillable = [
        'company_id',
        'asset_category_id',
        'coding_code',
        'is_active',
    ];


    protected function casts(): array
    {
        return [
            'company_id' =>
                'integer',

            'asset_category_id' =>
                'integer',

            'is_active' =>
                'boolean',
        ];
    }


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
}