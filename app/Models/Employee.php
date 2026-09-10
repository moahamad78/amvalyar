<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\OrganizationStructureGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Employee extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'department_id',
        'site_id',
        'manager_employee_id',
        'personnel_code',
        'first_name',
        'last_name',
        'display_name',
        'job_title',
        'national_code',
        'email',
        'phone',
        'is_active',
        'description',
        'location_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(
            function (Employee $employee): void {

                app(
                    OrganizationStructureGuard::class
                )->validateEmployee(
                    $employee
                );
            }
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function heldAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'custody_employee_id')->where('custody_type', 'employee');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class
        );
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(
            Site::class
        );
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'manager_employee_id'
        );
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(
            self::class,
            'manager_employee_id'
        );
    }
    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Location::class, 'location_id');
    }
}
