<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeliveryDisputeReplacementAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'replacements' => [
                'required',
                'array',
                'min:1',
            ],

            'replacements.*' => [
                'required',
                'integer',
                'distinct',
                'exists:assets,id',
            ],

            'replacement_note' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ];
    }
}