<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'display_name',
        'description',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }


    protected static function booted(): void
    {
        static::addGlobalScope(
            'tenant-role',
            function (Builder $builder): void {

                $user = auth()->user();

                if ($user === null) {
                    return;
                }

                if ($user->isSuperAdmin()) {
                    return;
                }

                if ($user->company_id === null) {

                    $builder->whereRaw(
                        '1 = 0'
                    );

                    return;
                }

                /*
                 * شرکت:
                 * Roleهای خودش + Roleهای سیستمی
                 */
                $builder->where(
                    function (Builder $query) use ($user): void {

                        $query
                            ->where(
                                'roles.company_id',
                                $user->company_id
                            )
                            ->orWhere(
                                function (Builder $system): void {

                                    $system
                                        ->whereNull(
                                            'roles.company_id'
                                        )
                                        ->where(
                                            'roles.is_system',
                                            true
                                        );
                                }
                            );
                    }
                );
            }
        );


        static::creating(
            function (Role $role): void {

                $user = auth()->user();

                if ($user === null) {
                    return;
                }

                /*
                 * مدیر شرکت فقط Role شرکتی می‌سازد.
                 */
                if (!$user->isSuperAdmin()) {

                    $role->company_id =
                        $user->company_id;

                    $role->is_system =
                        false;
                }
            }
        );


        static::updating(
            function (Role $role): void {

                $user = auth()->user();

                if (
                    $user === null
                    || $user->isSuperAdmin()
                ) {
                    return;
                }

                if ($role->isDirty('company_id')) {

                    $role->company_id =
                        $role->getOriginal(
                            'company_id'
                        );
                }

                $role->is_system =
                    false;
            }
        );
    }


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }


    public function users(): HasMany
    {
        return $this->hasMany(
            User::class
        );
    }


    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions'
        );
    }


    public function isActive(): bool
    {
        return $this->is_active === true;
    }


    public function isSystem(): bool
    {
        return $this->is_system === true;
    }


    public function hasPermission(
        string $permissionName
    ): bool {
        return $this->permissions()
            ->where(
                'name',
                $permissionName
            )
            ->where(
                'permissions.is_active',
                true
            )
            ->exists();
    }
}