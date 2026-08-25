<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WorkflowActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }


    public function rules(): array
    {
        return [

            'action' => [
                'required',
                Rule::in([
                    'approve',
                    'reject',
                ]),
            ],

            'comment' => [
                'nullable',
                'string',
                'max:2000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Inventory Request Item Decisions
            |--------------------------------------------------------------------------
            */

            'items' => [
                'nullable',
                'array',
            ],

            'items.*.approved_quantity' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999999',
            ],

            'items.*.decision_note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }


    public function messages(): array
    {
        return [

            'action.required' =>
                'نوع اقدام مشخص نشده است.',

            'action.in' =>
                'نوع اقدام معتبر نیست.',

            'comment.max' =>
                'توضیحات بیش از حد مجاز است.',

            'items.*.approved_quantity.numeric' =>
                'تعداد تأییدشده باید عدد باشد.',

            'items.*.approved_quantity.min' =>
                'تعداد تأییدشده نمی‌تواند منفی باشد.',

            'items.*.decision_note.max' =>
                'توضیح هر قلم بیش از حد مجاز است.',
        ];
    }
}