<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class InventoryRequestGuard
{
    public function validate(
        int $companyId,
        ?Employee $employee,
        ?User $user,
        ?Site $site,
        ?Department $department
    ): void {

        if (
            $employee !== null
            &&
            (int) $employee->company_id
            !==
            $companyId
        ) {

            throw ValidationException::withMessages([
                'requester_employee_id' =>
                    'پرسنل درخواست‌کننده متعلق به شرکت درخواست نیست.',
            ]);
        }


        if (
            $user !== null
            &&
            !$user->isSuperAdmin()
            &&
            (int) $user->company_id
            !==
            $companyId
        ) {

            throw ValidationException::withMessages([
                'requester_user_id' =>
                    'کاربر درخواست‌کننده متعلق به شرکت درخواست نیست.',
            ]);
        }


        if (
            $site !== null
            &&
            (
                (int) $site->company_id
                !==
                $companyId
                ||
                !$site->is_active
            )
        ) {

            throw ValidationException::withMessages([
                'site_id' =>
                    'سایت انتخاب‌شده معتبر، فعال یا متعلق به شرکت درخواست نیست.',
            ]);
        }


        if (
            $department !== null
            &&
            (
                (int) $department->company_id
                !==
                $companyId
                ||
                !$department->is_active
            )
        ) {

            throw ValidationException::withMessages([
                'department_id' =>
                    'واحد سازمانی انتخاب‌شده معتبر، فعال یا متعلق به شرکت درخواست نیست.',
            ]);
        }
    }
}