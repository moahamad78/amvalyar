<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AssetCodeSubNature extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'main_nature_id',
        'name',
        'code',
        'description',
        'is_active',
        'sort_order',
    ];


    protected function casts(): array
    {
        return [
            'company_id' =>
                'integer',

            'main_nature_id' =>
                'integer',

            'is_active' =>
                'boolean',

            'sort_order' =>
                'integer',
        ];
    }


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }


    public function mainNature(): BelongsTo
    {
        return $this->belongsTo(
            AssetCodeMainNature::class,
            'main_nature_id'
        );
    }


    public function assets(): HasMany
    {
        return $this->hasMany(
            Asset::class,
            'code_sub_nature_id'
        );
    }
}