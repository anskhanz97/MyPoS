<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostPurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], 'returned_at' => ['required', 'date'], 'reason' => ['required', 'string', 'min:3', 'max:500'],
            'credit_status' => ['required', 'in:expected,confirmed'], 'supplier_credit_reference' => ['nullable', 'required_if:credit_status,confirmed', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1', 'max:500'], 'lines.*.purchase_receipt_line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/', 'not_in:0,0.0,0.00,0.000,0.0000,0.00000,0.000000'],
        ];
    }
}
