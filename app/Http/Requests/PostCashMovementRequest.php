<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], 'kind' => ['required', 'in:paid_in,paid_out,supplier_payment,no_sale_open'],
            'amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'], 'reason' => ['required', 'string', 'min:3', 'max:500'],
            'occurred_at' => ['required', 'date'], 'source_type' => ['nullable', 'string', 'max:80'], 'source_id' => ['nullable', 'uuid'],
        ];
    }
}
