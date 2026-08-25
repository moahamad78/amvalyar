<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        $user = $this->user();

        $site =
            $this->route('site');


        $companyId = null;


        if ($site instanceof Site) {

            $companyId =
                $site->company_id;
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
                'sites',
                'code'
            );


        if ($companyId !== null) {

            $codeRule->where(
                'company_id',
                $companyId
            );
        }


        if ($site instanceof Site) {

            $codeRule->ignore(
                $site->id
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

            'type' => [
                'required',

                Rule::in([
                    'factory',
                    'office',
                    'warehouse',
                    'branch',
                    'site',
                    'other',
                ]),
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
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
            !($site instanceof Site)
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
                'نام سایت الزامی است.',


            'code.required' =>
                'کد سایت الزامی است.',

            'code.unique' =>
                'این کد سایت قبلاً در همین شرکت استفاده شده است.',


            'type.required' =>
                'نوع سایت را انتخاب کنید.',

            'type.in' =>
                'نوع سایت انتخاب‌شده معتبر نیست.',


            'sort_order.integer' =>
                'ترتیب نمایش باید عدد باشد.',

            'sort_order.min' =>
                'ترتیب نمایش نمی‌تواند منفی باشد.',
        ];
    }
}