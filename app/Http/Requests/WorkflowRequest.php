<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        $workflow =
            $this->route('workflow');

        $user =
            $this->user();


        if (
            $workflow instanceof Workflow
        ) {

            $companyId =
                $workflow->company_id;

        } elseif (
            $user !== null
            &&
            !$user->isSuperAdmin()
        ) {

            $companyId =
                $user->company_id;

        } else {

            $companyId =
                $this->integer('company_id');
        }


        $codeRule =
            Rule::unique(
                'workflows',
                'code'
            )
            ->where(
                'company_id',
                $companyId
            );


        if (
            $workflow instanceof Workflow
        ) {

            $codeRule->ignore(
                $workflow->id
            );
        }


        $rules = [

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:100',
                $codeRule,
            ],

            'process_type' => [
                'required',
                'string',
                Rule::in([
                    'asset_request',
                    'asset_delivery',
                    'asset_transfer',
                    'asset_return',
                    'asset_disposal',
                    'asset_repair',
                    'inventory_request',
                    'custom',
                ]),
            ],

            'description' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'is_default' => [
                'nullable',
                'boolean',
            ],
        ];


        if (
            $user?->isSuperAdmin()
            &&
            !($workflow instanceof Workflow)
        ) {

            $rules['company_id'] = [
                'required',
                'integer',
                'exists:companies,id',
            ];
        }


        return $rules;
    }


    public function messages(): array
    {
        return [

            'company_id.required' =>
                'انتخاب شرکت الزامی است.',

            'company_id.exists' =>
                'شرکت انتخاب‌شده معتبر نیست.',

            'name.required' =>
                'نام گردش کاری الزامی است.',

            'code.required' =>
                'کد گردش کاری الزامی است.',

            'code.unique' =>
                'این کد گردش کاری قبلاً در این شرکت استفاده شده است.',

            'process_type.required' =>
                'نوع فرآیند الزامی است.',

            'process_type.in' =>
                'نوع فرآیند انتخاب‌شده معتبر نیست.',
        ];
    }
}