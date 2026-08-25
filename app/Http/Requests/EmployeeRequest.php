<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        $user =
            $this->user();

        $employee =
            $this->route('employee');


        $companyId = null;


        if ($employee instanceof Employee) {

            $companyId =
                $employee->company_id;
        }
        elseif (
            $user !== null
            &&
            !$user->isSuperAdmin()
        ) {

            $companyId =
                $user->company_id;
        }
        elseif ($user?->isSuperAdmin()) {

            $companyId =
                $this->integer('company_id');
        }


        /*
        |--------------------------------------------------------------------------
        | Personnel Code
        |--------------------------------------------------------------------------
        */

        $personnelCodeRule =
            Rule::unique(
                'employees',
                'personnel_code'
            );


        if ($companyId !== null) {

            $personnelCodeRule->where(
                'company_id',
                $companyId
            );
        }


        if ($employee instanceof Employee) {

            $personnelCodeRule->ignore(
                $employee->id
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Linked User
        |--------------------------------------------------------------------------
        |
        | هر User حداکثر به یک Employee متصل می‌شود.
        |
        */

        $userRule =
            Rule::unique(
                'employees',
                'user_id'
            );


        if ($employee instanceof Employee) {

            $userRule->ignore(
                $employee->id
            );
        }


        /*
        |--------------------------------------------------------------------------
        | National Code
        |--------------------------------------------------------------------------
        */

        $nationalCodeRule =
            Rule::unique(
                'employees',
                'national_code'
            );


        if ($companyId !== null) {

            $nationalCodeRule->where(
                'company_id',
                $companyId
            );
        }


        if ($employee instanceof Employee) {

            $nationalCodeRule->ignore(
                $employee->id
            );
        }


        $rules = [

            'personnel_code' => [
                'required',
                'string',
                'max:50',
                $personnelCodeRule,
            ],

            'first_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'last_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'display_name' => [
                'required',
                'string',
                'max:255',
            ],

            'job_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'national_code' => [
                'nullable',
                'string',
                'max:20',
                $nationalCodeRule,
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'site_id' => [
                'nullable',
                'integer',
                'exists:sites,id',
            ],

            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],
            'manager_employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
            ],

            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                $userRule,
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];


        if (
            $user?->isSuperAdmin()
            &&
            !($employee instanceof Employee)
        ) {

            $rules['company_id'] = [
                'required',
                'integer',
                'exists:companies,id',
            ];
        }


        return $rules;
    }


    public function messages(): array
    {
        return [

            'company_id.required' =>
                'انتخاب شرکت الزامی است.',

            'company_id.exists' =>
                'شرکت انتخاب‌شده معتبر نیست.',


            'personnel_code.required' =>
                'کد پرسنلی الزامی است.',

            'personnel_code.unique' =>
                'این کد پرسنلی قبلاً در همین شرکت ثبت شده است.',


            'display_name.required' =>
                'نام نمایشی پرسنل الزامی است.',


            'national_code.unique' =>
                'این کد ملی قبلاً برای پرسنل دیگری در همین شرکت ثبت شده است.',


            'email.email' =>
                'فرمت ایمیل معتبر نیست.',


            'site_id.exists' =>
                'سایت انتخاب‌شده معتبر نیست.',

            'department_id.exists' =>
                'واحد سازمانی انتخاب‌شده معتبر نیست.',

            'location_id.exists' =>
                'محل استقرار انتخاب‌شده معتبر نیست.',
            'manager_employee_id.exists' =>
                'مدیر مستقیم انتخاب‌شده معتبر نیست.',


            'user_id.exists' =>
                'حساب کاربری انتخاب‌شده معتبر نیست.',

            'user_id.unique' =>
                'این حساب کاربری قبلاً به پرسنل دیگری متصل شده است.',
        ];
    }
}