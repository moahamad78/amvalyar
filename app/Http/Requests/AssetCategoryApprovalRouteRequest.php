<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssetCategoryApprovalRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'asset_category_id' => [
                'required',
                'integer',
                'exists:asset_categories,id',
            ],

            'approver_type' => [
                'required',
                Rule::in([
                    'role',
                    'employee',
                ]),
            ],

            'role_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'employee_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'is_required' => [
                'nullable',
                'boolean',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:100000',
            ],
        ];
    }
}