<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\JalaliDate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin()
            ?? false;
    }


    public function rules(): array
    {
        $companyId =
            $this->route('company')?->id;


        $jalaliDateRule =
            function (
                string $attribute,
                mixed $value,
                Closure $fail
            ): void {

                if (
                    $value === null
                    || trim((string) $value) === ''
                ) {
                    return;
                }

                try {

                    JalaliDate::toGregorianDate(
                        (string) $value
                    );

                } catch (\Throwable) {

                    $fail(
                        'تاریخ واردشده معتبر نیست. نمونه صحیح: 1405/05/16'
                    );
                }
            };


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

                Rule::unique(
                    'companies',
                    'code'
                )->ignore($companyId),
            ],

            'manager_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'national_id' => [
                'nullable',
                'string',
                'max:50',
            ],

            'economic_code' => [
                'nullable',
                'string',
                'max:50',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'license_start' => [
                'nullable',
                'string',
                $jalaliDateRule,
            ],

            'license_end' => [
                'nullable',
                'string',
                $jalaliDateRule,
            ],

            'max_users' => [
                'required',
                'integer',
                'min:1',
            ],

            'max_assets' => [
                'required',
                'integer',
                'min:1',
            ],

            'plan' => [
                'required',

                Rule::in([
                    'basic',
                    'professional',
                    'enterprise',
                    'demo',
                    'internal',
                ]),
            ],

            'status' => [
                'required',

                Rule::in([
                    'active',
                    'suspended',
                    'expired',
                    'demo',
                ]),
            ],
        ];


        if ($this->isMethod('POST')) {

            $rules += [

                'admin_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'admin_username' => [
                    'required',
                    'string',
                    'max:100',

                    Rule::unique(
                        'users',
                        'username'
                    ),
                ],

                'admin_email' => [
                    'required',
                    'email',
                    'max:255',

                    Rule::unique(
                        'users',
                        'email'
                    ),
                ],

                'admin_password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],
            ];
        }


        return $rules;
    }


    public function withValidator(
        $validator
    ): void {
        $validator->after(
            function ($validator): void {

                $start =
                    $this->input(
                        'license_start'
                    );

                $end =
                    $this->input(
                        'license_end'
                    );


                if (
                    !$start
                    || !$end
                ) {
                    return;
                }


                try {

                    $startGregorian =
                        JalaliDate::toGregorianDate(
                            (string) $start
                        );

                    $endGregorian =
                        JalaliDate::toGregorianDate(
                            (string) $end
                        );

                } catch (\Throwable) {

                    return;
                }


                if (
                    $startGregorian !== null
                    &&
                    $endGregorian !== null
                    &&
                    $endGregorian < $startGregorian
                ) {

                    $validator
                        ->errors()
                        ->add(
                            'license_end',
                            'پایان اشتراک نمی‌تواند قبل از شروع اشتراک باشد.'
                        );
                }
            }
        );
    }


    public function messages(): array
    {
        return [

            'name.required' =>
                'نام شرکت الزامی است.',

            'code.required' =>
                'کد شرکت الزامی است.',

            'code.unique' =>
                'این کد شرکت قبلاً استفاده شده است.',

            'admin_name.required' =>
                'نام مدیر شرکت الزامی است.',

            'admin_username.required' =>
                'نام کاربری مدیر شرکت الزامی است.',

            'admin_username.unique' =>
                'این نام کاربری قبلاً استفاده شده است.',

            'admin_email.required' =>
                'ایمیل مدیر شرکت الزامی است.',

            'admin_email.email' =>
                'فرمت ایمیل مدیر شرکت صحیح نیست.',

            'admin_email.unique' =>
                'این ایمیل قبلاً استفاده شده است.',

            'admin_password.required' =>
                'رمز عبور مدیر شرکت الزامی است.',

            'admin_password.min' =>
                'رمز عبور باید حداقل ۸ کاراکتر باشد.',

            'admin_password.confirmed' =>
                'تکرار رمز عبور صحیح نیست.',
        ];
    }
}