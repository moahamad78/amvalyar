<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class RoleAccessService
{
    public function scope(User $user): string
    {
        if ($user->hasRole('employee')) {
            return 'self';
        }
        if ($user->isSuperAdmin()) {
            return 'company';
        }
        if (!$user->role?->is_active) {
            return 'self';
        }
        if ($user->hasPermission('assets.view_all')) {
            return 'company';
        }
        return match ($user->role?->name) {
            'admin', 'company_admin', 'finance_manager', 'accounting_head', 'inspection_head', 'ceo', 'asset_manager', 'chief_asset_keeper', 'warehouse_manager', 'warehouse_supervisor' => 'company',
            'unit_manager', 'engineering_head', 'production_planning_head', 'production_head', 'qc_rnd_head', 'administrative_head', 'factory_manager', 'commercial_manager', 'hr_manager', 'systems_manager' => 'department',
            default => 'self',
        };
    }

    public function financial(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->role?->is_active && in_array($user->role?->name, ['admin', 'company_admin', 'finance_manager', 'accounting_head', 'ceo'], true))
            || $user->hasPermission('assets.financial');
    }

    public function constrainAssets(Builder $query, User $user): void
    {
        $scope = $this->scope($user);
        if ($scope === 'company') {
            return;
        }
        $profiles = Employee::query()->where('company_id', $user->company_id)->where('user_id', $user->id);
        $employees = (clone $profiles)->pluck('id');
        $departments = $scope === 'department' ? (clone $profiles)->pluck('department_id')->filter() : collect();
        $query->where(function (Builder $visible) use ($query, $user, $employees, $departments): void {
            $visible->where($query->qualifyColumn('custody_user_id'), $user->id)
                ->orWhereIn($query->qualifyColumn('custody_employee_id'), $employees);
            if ($departments->isNotEmpty()) {
                $visible->orWhereIn($query->qualifyColumn('custody_department_id'), $departments);
            }
        });
    }
}
