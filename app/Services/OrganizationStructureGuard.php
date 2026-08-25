<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class OrganizationStructureGuard
{
    public function validateDepartment(
        Department $department
    ): void {
        $companyId =
            $this->resolveCompanyId(
                $department->company_id
            );

        if ($department->parent_id === null) {
            return;
        }

        if (
            $department->exists
            && (int) $department->parent_id
                === (int) $department->id
        ) {
            $this->fail(
                'parent_id',
                'یک واحد سازمانی نمی‌تواند والد خودش باشد.'
            );
        }

        $parent =
            Department::withoutGlobalScopes()
                ->find(
                    $department->parent_id
                );

        if ($parent === null) {
            $this->fail(
                'parent_id',
                'واحد سازمانی والد پیدا نشد.'
            );
        }

        if (
            (int) $parent->company_id
            !== (int) $companyId
        ) {
            $this->fail(
                'parent_id',
                'واحد سازمانی والد باید متعلق به همان شرکت باشد.'
            );
        }

        if (
            $department->exists
            && $this->departmentCreatesCycle(
                $department,
                $parent
            )
        ) {
            $this->fail(
                'parent_id',
                'این انتخاب باعث ایجاد حلقه در ساختار سازمانی می‌شود.'
            );
        }
    }


    public function validateLocation(
        Location $location
    ): void {
        $companyId =
            $this->resolveCompanyId(
                $location->company_id
            );

        $site =
            Site::withoutGlobalScopes()
                ->find(
                    $location->site_id
                );

        if ($site === null) {
            $this->fail(
                'site_id',
                'سایت انتخاب‌شده پیدا نشد.'
            );
        }

        if (
            (int) $site->company_id
            !== (int) $companyId
        ) {
            $this->fail(
                'site_id',
                'سایت انتخاب‌شده باید متعلق به همان شرکت باشد.'
            );
        }


        if ($location->parent_id === null) {
            return;
        }

        if (
            $location->exists
            && (int) $location->parent_id
                === (int) $location->id
        ) {
            $this->fail(
                'parent_id',
                'یک محل نمی‌تواند والد خودش باشد.'
            );
        }


        $parent =
            Location::withoutGlobalScopes()
                ->find(
                    $location->parent_id
                );

        if ($parent === null) {
            $this->fail(
                'parent_id',
                'محل والد پیدا نشد.'
            );
        }

        if (
            (int) $parent->company_id
            !== (int) $companyId
        ) {
            $this->fail(
                'parent_id',
                'محل والد باید متعلق به همان شرکت باشد.'
            );
        }

        if (
            (int) $parent->site_id
            !== (int) $location->site_id
        ) {
            $this->fail(
                'parent_id',
                'محل والد باید داخل همان سایت باشد.'
            );
        }

        if (
            $location->exists
            && $this->locationCreatesCycle(
                $location,
                $parent
            )
        ) {
            $this->fail(
                'parent_id',
                'این انتخاب باعث ایجاد حلقه در ساختار محل‌ها می‌شود.'
            );
        }
    }


    public function validateEmployee(
        Employee $employee
    ): void {
        $companyId =
            $this->resolveCompanyId(
                $employee->company_id
            );


        /*
        |--------------------------------------------------------------------------
        | USER
        |--------------------------------------------------------------------------
        */

        if ($employee->user_id !== null) {

            $user =
                User::withoutGlobalScopes()
                    ->find(
                        $employee->user_id
                    );

            if ($user === null) {
                $this->fail(
                    'user_id',
                    'حساب کاربری انتخاب‌شده پیدا نشد.'
                );
            }

            if ($user->is_super_admin) {
                $this->fail(
                    'user_id',
                    'Super Admin نمی‌تواند به‌عنوان پرسنل یک شرکت ثبت شود.'
                );
            }

            if (
                (int) $user->company_id
                !== (int) $companyId
            ) {
                $this->fail(
                    'user_id',
                    'حساب کاربری باید متعلق به همان شرکت باشد.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DEPARTMENT
        |--------------------------------------------------------------------------
        */

        if ($employee->department_id !== null) {

            $department =
                Department::withoutGlobalScopes()
                    ->find(
                        $employee->department_id
                    );

            if ($department === null) {
                $this->fail(
                    'department_id',
                    'واحد سازمانی انتخاب‌شده پیدا نشد.'
                );
            }

            if (
                (int) $department->company_id
                !== (int) $companyId
            ) {
                $this->fail(
                    'department_id',
                    'واحد سازمانی باید متعلق به همان شرکت باشد.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SITE
        |--------------------------------------------------------------------------
        */

        if ($employee->site_id !== null) {

            $site =
                Site::withoutGlobalScopes()
                    ->find(
                        $employee->site_id
                    );

            if ($site === null) {
                $this->fail(
                    'site_id',
                    'سایت انتخاب‌شده پیدا نشد.'
                );
            }

            if (
                (int) $site->company_id
                !== (int) $companyId
            ) {
                $this->fail(
                    'site_id',
                    'سایت پرسنل باید متعلق به همان شرکت باشد.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | LOCATION
        |--------------------------------------------------------------------------
        */

        if ($employee->location_id !== null) {

            $location =
                Location::withoutGlobalScopes()
                    ->find(
                        $employee->location_id
                    );

            if ($location === null) {
                $this->fail(
                    'location_id',
                    'محل استقرار انتخاب‌شده پیدا نشد.'
                );
            }

            if (
                (int) $location->company_id
                !== (int) $companyId
            ) {
                $this->fail(
                    'location_id',
                    'محل استقرار پرسنل باید متعلق به همان شرکت باشد.'
                );
            }

            if (!$location->is_active) {
                $this->fail(
                    'location_id',
                    'محل استقرار انتخاب‌شده غیرفعال است.'
                );
            }

            if (
                $employee->site_id !== null
                &&
                (int) $location->site_id
                !== (int) $employee->site_id
            ) {
                $this->fail(
                    'location_id',
                    'محل استقرار باید داخل سایت انتخاب‌شده پرسنل باشد.'
                );
            }

            /*
             * اگر برای پرسنل سایت خالی باشد ولی Location مشخص باشد،
             * Site به‌صورت خودکار از Location گرفته می‌شود.
             */
            if ($employee->site_id === null) {
                $employee->site_id =
                    $location->site_id;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | MANAGER
        |--------------------------------------------------------------------------
        */

        if ($employee->manager_employee_id !== null) {

            if (
                $employee->exists
                && (int) $employee->manager_employee_id
                    === (int) $employee->id
            ) {
                $this->fail(
                    'manager_employee_id',
                    'یک پرسنل نمی‌تواند مدیر خودش باشد.'
                );
            }

            $manager =
                Employee::withoutGlobalScopes()
                    ->find(
                        $employee->manager_employee_id
                    );

            if ($manager === null) {
                $this->fail(
                    'manager_employee_id',
                    'مدیر انتخاب‌شده پیدا نشد.'
                );
            }

            if (
                (int) $manager->company_id
                !== (int) $companyId
            ) {
                $this->fail(
                    'manager_employee_id',
                    'مدیر پرسنل باید متعلق به همان شرکت باشد.'
                );
            }

            if (
                $employee->exists
                && $this->employeeCreatesCycle(
                    $employee,
                    $manager
                )
            ) {
                $this->fail(
                    'manager_employee_id',
                    'این انتخاب باعث ایجاد حلقه در زنجیره مدیریتی می‌شود.'
                );
            }
        }
    }


    private function resolveCompanyId(
        mixed $modelCompanyId
    ): int {
        $user = auth()->user();

        if (
            $user !== null
            && !$user->isSuperAdmin()
        ) {
            if ($user->company_id === null) {
                $this->fail(
                    'company_id',
                    'کاربر به هیچ شرکتی متصل نیست.'
                );
            }

            return (int) $user->company_id;
        }

        if ($modelCompanyId === null) {
            $this->fail(
                'company_id',
                'انتخاب شرکت الزامی است.'
            );
        }

        return (int) $modelCompanyId;
    }


    private function departmentCreatesCycle(
        Department $department,
        Department $parent
    ): bool {
        $current = $parent;

        while ($current !== null) {

            if (
                (int) $current->id
                === (int) $department->id
            ) {
                return true;
            }

            if ($current->parent_id === null) {
                break;
            }

            $current =
                Department::withoutGlobalScopes()
                    ->find(
                        $current->parent_id
                    );
        }

        return false;
    }


    private function locationCreatesCycle(
        Location $location,
        Location $parent
    ): bool {
        $current = $parent;

        while ($current !== null) {

            if (
                (int) $current->id
                === (int) $location->id
            ) {
                return true;
            }

            if ($current->parent_id === null) {
                break;
            }

            $current =
                Location::withoutGlobalScopes()
                    ->find(
                        $current->parent_id
                    );
        }

        return false;
    }


    private function employeeCreatesCycle(
        Employee $employee,
        Employee $manager
    ): bool {
        $current = $manager;

        while ($current !== null) {

            if (
                (int) $current->id
                === (int) $employee->id
            ) {
                return true;
            }

            if ($current->manager_employee_id === null) {
                break;
            }

            $current =
                Employee::withoutGlobalScopes()
                    ->find(
                        $current->manager_employee_id
                    );
        }

        return false;
    }


    private function fail(
        string $field,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }
}