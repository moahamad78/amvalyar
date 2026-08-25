<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WarehouseAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        return [

            'allocations' => [
                'nullable',
                'array',
            ],

            'allocations.*' => [
                'nullable',
                'array',
            ],

            'allocations.*.*' => [
                'integer',
                'distinct',
                'exists:assets,id',
            ],
        ];
    }


    public function messages(): array
    {
        return [

            'allocations.*.*.integer' =>
                'دارایی انتخاب‌شده معتبر نیست.',

            'allocations.*.*.distinct' =>
                'یک دارایی را نمی‌توان برای بیش از یک قلم انتخاب کرد.',

            'allocations.*.*.exists' =>
                'یکی از دارایی‌های انتخاب‌شده در سیستم وجود ندارد.',
        ];
    }
}