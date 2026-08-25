<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        $user =
            $this->user();

        $location =
            $this->route(
                'location'
            );


        $companyId = null;


        if (
            $location
            instanceof Location
        ) {
            $companyId =
                $location->company_id;
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


        /*
        |--------------------------------------------------------------------------
        | Location Code
        |--------------------------------------------------------------------------
        |
        | کد محل داخل هر Site یکتا است.
        |
        */

        $codeRule =
            Rule::unique(
                'locations',
                'code'
            );


        if ($companyId !== null) {

            $codeRule->where(
                'company_id',
                $companyId
            );
        }


        if ($this->filled('site_id')) {

            $codeRule->where(
                'site_id',
                $this->integer(
                    'site_id'
                )
            );
        }


        if (
            $location
            instanceof Location
        ) {
            $codeRule->ignore(
                $location->id
            );
        }


        $rules = [

            'site_id' => [
                'required',
                'integer',
                'exists:sites,id',
            ],

            'parent_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],

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

            'type' => [
                'required',

                Rule::in([
                    'building',
                    'floor',
                    'room',
                    'hall',
                    'warehouse',
                    'production_line',
                    'yard',
                    'office',
                    'location',
                    'other',
                ]),
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
                $location
                instanceof Location
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


            'site_id.required' =>
                'انتخاب سایت الزامی است.',

            'site_id.exists' =>
                'سایت انتخاب‌شده معتبر نیست.',


            'parent_id.exists' =>
                'محل بالادستی انتخاب‌شده معتبر نیست.',


            'name.required' =>
                'نام محل الزامی است.',


            'code.required' =>
                'کد محل الزامی است.',

            'code.unique' =>
                'این کد قبلاً در همین سایت استفاده شده است.',


            'type.required' =>
                'نوع محل را انتخاب کنید.',

            'type.in' =>
                'نوع محل انتخاب‌شده معتبر نیست.',


            'sort_order.integer' =>
                'ترتیب نمایش باید عدد باشد.',

            'sort_order.min' =>
                'ترتیب نمایش نمی‌تواند منفی باشد.',
        ];
    }
}