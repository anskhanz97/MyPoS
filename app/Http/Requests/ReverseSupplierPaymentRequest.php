<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReverseSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'min:3', 'max:500'], 'reversed_at' => ['required', 'date']];
    }
}
