<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DeliveryDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reason_code' => [
                'required',
                Rule::in([
                    'damaged',
                    'wrong_item',
                    'missing_parts',
                    'quantity_mismatch',
                    'wrong_organizational_destination',
                    'other',
                ]),
            ],

            'description' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'dispute_allocations' => [
                'required',
                'array',
                'min:1',
            ],

            'dispute_allocations.*' => [
                'integer',
                'distinct',
                'exists:inventory_request_allocations,id',
            ],

            'item_issue_type' => [
                'nullable',
                'array',
            ],

            'item_issue_type.*' => [
                'nullable',
                Rule::in([
                    'damaged',
                    'wrong_item',
                    'missing_parts',
                    'quantity_mismatch',
                    'wrong_organizational_destination',
                    'other',
                ]),
            ],

            'item_description' => [
                'nullable',
                'array',
            ],

            'item_description.*' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}