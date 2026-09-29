<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HandoverCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'to_membership_id' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:500']];
    }
}
