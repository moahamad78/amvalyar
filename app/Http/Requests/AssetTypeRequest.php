<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssetTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return
            $user->isSuperAdmin()
            || $user->hasPermission(
                'asset_types.manage'
            );
    }


    public function rules(): array
    {
        $typeId =
            $this->route('type')?->id
            ?? $this->route('asset_type')?->id
            ?? $this->route('assetType')?->id;

        $companyId =
            $this->resolvedCompanyId();

        return [
            'asset_category_id' => [
                'required',
                'integer',
                Rule::exists(
                    'asset_categories',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                ),
            ],

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
                    'asset_types',
                    'code'
                )
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'company_id',
                                $companyId
                            )
                    )
                    ->ignore($typeId),
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

            'company_id' => [
                'nullable',
                'integer',
                'exists:companies,id',
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
                    : 10,

            'is_active' =>
                $this->boolean('is_active'),
        ]);
    }


    public function resolvedCompanyId(): int
    {
        $user = $this->user();

        if ($user === null) {
            return 0;
        }

        if ($user->isSuperAdmin()) {
            return (int) $this->input(
                'company_id'
            );
        }

        return (int) $user->company_id;
    }
}