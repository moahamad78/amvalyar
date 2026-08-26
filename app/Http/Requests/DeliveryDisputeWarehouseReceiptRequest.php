<?php
declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeliveryDisputeWarehouseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'received_items' => ['required', 'array', 'min:1'],
            'received_items.*' => ['integer', 'distinct', 'exists:delivery_dispute_items,id'],
            'warehouse_note' => ['nullable', 'string', 'max:4000'],
        ];
    }
}