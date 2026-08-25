<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssetAttributeDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],

            'data_type' => [
                'required',
                Rule::in([
                    'text',
                    'textarea',
                    'number',
                    'date',
                    'boolean',
                    'select',
                    'photo',
                ]),
            ],

            'required_stage' => [
                'required',
                Rule::in([
                    'optional',
                    'warehouse_entry',
                    'asset_manager_review',
                    'before_delivery',
                ]),
            ],

            'unit' => [
                'nullable',
                'string',
                'max:100',
            ],

            'placeholder' => [
                'nullable',
                'string',
                'max:255',
            ],

            'help_text' => [
                'nullable',
                'string',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Select options
            |--------------------------------------------------------------------------
            |
            | هر خط:
            |
            | label|value
            |
            | مثال:
            | 16 گیگ|16
            |
            | اگر | نوشته نشود خود متن هم label و هم value می‌شود.
            |
            */

            'options_text' => [
                'nullable',
                'string',
            ],
        ];
    }


    public function messages(): array
    {
        return [

            'name.required' =>
                'عنوان ویژگی الزامی است.',

            'code.required' =>
                'کد ویژگی الزامی است.',

            'code.regex' =>
                'کد ویژگی فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',

            'data_type.required' =>
                'نوع فیلد الزامی است.',

            'required_stage.required' =>
                'مرحله الزام را مشخص کنید.',
        ];
    }
}