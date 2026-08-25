<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OrganizationalAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'operation' => [
                'required',
                Rule::in([
                    'assign',
                    'relocate',
                    'return',
                ]),
            ],

            'asset_id' => [
                'required',
                'integer',
                'exists:assets,id',
            ],

            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'site_id' => [
                'nullable',
                'integer',
                'exists:sites,id',
            ],

            'location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
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
            'operation.required' =>
                'نوع عملیات الزامی است.',

            'operation.in' =>
                'نوع عملیات معتبر نیست.',

            'asset_id.required' =>
                'انتخاب دارایی الزامی است.',

            'asset_id.exists' =>
                'دارایی انتخاب‌شده معتبر نیست.',

            'department_id.exists' =>
                'واحد سازمانی انتخاب‌شده معتبر نیست.',

            'site_id.exists' =>
                'سایت انتخاب‌شده معتبر نیست.',

            'location_id.exists' =>
                'محل انتخاب‌شده معتبر نیست.',
        ];
    }
}