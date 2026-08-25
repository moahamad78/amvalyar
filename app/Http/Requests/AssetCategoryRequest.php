<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        /*
         * Categories are global reference data.
         * Only Super Admin can mutate them.
         */
        return $user->isSuperAdmin();
    }


    public function rules(): array
    {
        $categoryId =
            $this->route('category')?->id
            ?? $this->route('asset_category')?->id
            ?? $this->route('assetCategory')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique(
                    'asset_categories',
                    'code'
                )->ignore($categoryId),
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:1000000',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }


    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' =>
                strtoupper(
                    trim(
                        (string) $this->input('code')
                    )
                ),

            'sort_order' =>
                $this->filled('sort_order')
                    ? (int) $this->input('sort_order')
                    : 0,

            'is_active' =>
                $this->boolean('is_active'),
        ]);
    }
}