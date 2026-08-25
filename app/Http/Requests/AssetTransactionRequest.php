<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AssetTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        return [

            'asset_id' => [
                'required',
                'integer',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {

                    $currentUser = $this->user();

                    if ($currentUser === null) {

                        $fail(
                            'کاربر وارد سامانه نشده است.'
                        );

                        return;
                    }


                    $query = DB::table('assets')
                        ->where(
                            'id',
                            (int) $value
                        );


                    /*
                     * کاربر شرکتی فقط Asset
                     * شرکت خودش را معتبر می‌بیند.
                     */
                    if (
                        !$currentUser->isSuperAdmin()
                    ) {

                        $query->where(
                            'company_id',
                            $currentUser->company_id
                        );
                    }


                    if (!$query->exists()) {

                        $fail(
                            'دارایی انتخاب‌شده معتبر نیست یا به شرکت شما تعلق ندارد.'
                        );
                    }
                },
            ],


            'to_user_id' => [
                'nullable',
                'integer',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {

                    /*
                     * برای عملیات‌هایی که User
                     * نیاز ندارند مقدار خالی مجاز است.
                     */
                    if (
                        $value === null
                        || $value === ''
                    ) {
                        return;
                    }


                    $currentUser = $this->user();

                    if ($currentUser === null) {

                        $fail(
                            'کاربر وارد سامانه نشده است.'
                        );

                        return;
                    }


                    $query = DB::table('users')
                        ->where(
                            'id',
                            (int) $value
                        )
                        ->where(
                            'is_super_admin',
                            false
                        )
                        ->where(
                            'is_active',
                            true
                        );


                    /*
                     * برای Tenant فقط کاربران
                     * همان شرکت قابل انتخاب‌اند.
                     */
                    if (
                        !$currentUser->isSuperAdmin()
                    ) {

                        $query->where(
                            'company_id',
                            $currentUser->company_id
                        );
                    }


                    if (!$query->exists()) {

                        $fail(
                            'کاربر انتخاب‌شده معتبر نیست یا به شرکت مربوطه تعلق ندارد.'
                        );
                    }
                },
            ],


            'type' => [
                'required',

                Rule::in([
                    'delivery',
                    'return',
                    'transfer',
                    'destroy',
                ]),
            ],


            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }


    public function messages(): array
    {
        return [

            'asset_id.required' =>
                'انتخاب دارایی الزامی است.',

            'asset_id.integer' =>
                'شناسه دارایی معتبر نیست.',


            'to_user_id.integer' =>
                'شناسه کاربر معتبر نیست.',


            'type.required' =>
                'نوع عملیات را انتخاب کنید.',

            'type.in' =>
                'نوع عملیات انتخاب‌شده معتبر نیست.',


            'description.max' =>
                'توضیحات نمی‌تواند بیشتر از ۲۰۰۰ کاراکتر باشد.',
        ];
    }
}