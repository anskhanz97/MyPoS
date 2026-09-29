<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], 'amount' => ['required', 'string', 'regex:/^[1-9][0-9]{0,14}(\.[0-9]{1,2})?$/'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque,card,other'], 'reference' => ['required', 'string', 'max:120'],
            'paid_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000'], 'allocations' => ['present', 'array', 'max:500'],
            'allocations.*.supplier_charge_id' => ['required', 'uuid'], 'allocations.*.amount' => ['required', 'string', 'regex:/^[1-9][0-9]{0,14}(\.[0-9]{1,2})?$/'],
        ];
    }
}
