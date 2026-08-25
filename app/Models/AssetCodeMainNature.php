<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AssetCodeMainNature extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
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


    public function subNatures(): HasMany
    {
        return $this->hasMany(
            AssetCodeSubNature::class,
            'main_nature_id'
        )
            ->orderBy(
                'sort_order'
            )
            ->orderBy(
                'id'
            );
    }


    public function assets(): HasMany
    {
        return $this->hasMany(
            Asset::class,
            'code_main_nature_id'
        );
    }
}