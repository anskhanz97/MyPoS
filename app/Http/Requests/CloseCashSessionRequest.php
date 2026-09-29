<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'],
            'counted_amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'denominations' => ['nullable', 'array', 'max:30'], 'denominations.*' => ['required', 'integer', 'min:0', 'max:1000000'],
            'pending_server_sale_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'pending_offline_sale_count' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
