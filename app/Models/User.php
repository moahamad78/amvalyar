<?php

declare(strict_types=1);

namespace App\Models;


use App\Models\Concerns\EnforcesCompanyUserLimit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, EnforcesCompanyUserLimit;

    protected $fillable = [
        'company_id',
        'username',
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRole('admin');
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->role?->hasPermission(
            $permissionName
        ) ?? false;
    }

    public function hasAnyPermission(
        array $permissionNames
    ): bool {
        foreach ($permissionNames as $permissionName) {
            if ($this->hasPermission($permissionName)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(
        array $permissionNames
    ): bool {
        foreach ($permissionNames as $permissionName) {
            if (!$this->hasPermission($permissionName)) {
                return false;
            }
        }

        return true;
    }
}