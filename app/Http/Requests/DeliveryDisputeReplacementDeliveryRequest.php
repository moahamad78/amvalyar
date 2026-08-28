<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeliveryDisputeReplacementDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'delivery_note' => [
                'nullable',
                'string',
                'max:4000',
            ],
        ];
    }
}