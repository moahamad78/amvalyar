<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Company extends Model
{
    protected $fillable = [
        'name',
        'code',
        'manager_name',
        'phone',
        'email',
        'national_id',
        'economic_code',
        'address',
        'license_start',
        'license_end',
        'max_users',
        'max_assets',
        'plan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'license_start' => 'date',
            'license_end' => 'date',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function storageProfiles(): HasMany
    {
        return $this->hasMany(CompanyStorageProfile::class);
    }
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function assetTransactions(): HasMany
    {
        return $this->hasMany(
            AssetTransaction::class
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDemo(): bool
    {
        return $this->status === 'demo';
    }

    public function isExpired(): bool
    {
        return $this->license_end !== null
            && $this->license_end->isPast();
    }
}