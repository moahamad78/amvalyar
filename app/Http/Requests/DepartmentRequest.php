<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        $user =
            $this->user();

        $department =
            $this->route(
                'department'
            );


        $companyId = null;


        if (
            $department
            instanceof Department
        ) {

            $companyId =
                $department->company_id;
        }
        elseif (
            $user !== null
            &&
            !$user->isSuperAdmin()
        ) {

            $companyId =
                $user->company_id;
        }
        elseif (
            $user?->isSuperAdmin()
        ) {

            $companyId =
                $this->integer(
                    'company_id'
                );
        }


        $codeRule =
            Rule::unique(
                'departments',
                'code'
            );


        if ($companyId !== null) {

            $codeRule->where(
                'company_id',
                $companyId
            );
        }


        if (
            $department
            instanceof Department
        ) {

            $codeRule->ignore(
                $department->id
            );
        }


        $rules = [

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                $codeRule,
            ],

            'parent_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
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

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999',
            ],
        ];


        if (
            $user?->isSuperAdmin()
            &&
            !(
                $department
                instanceof Department
            )
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


            'name.required' =>
                'نام واحد سازمانی الزامی است.',


            'code.required' =>
                'کد واحد سازمانی الزامی است.',

            'code.unique' =>
                'این کد قبلاً در همین شرکت استفاده شده است.',


            'parent_id.exists' =>
                'واحد بالادستی انتخاب‌شده معتبر نیست.',


            'sort_order.integer' =>
                'ترتیب نمایش باید عدد باشد.',

            'sort_order.min' =>
                'ترتیب نمایش نمی‌تواند منفی باشد.',
        ];
    }
}