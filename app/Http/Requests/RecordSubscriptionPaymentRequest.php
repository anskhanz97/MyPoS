<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordSubscriptionPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], 'reference' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'], 'paid_on' => ['required', 'date'],
            'periods' => ['required', 'integer', 'min:1', 'max:24'], 'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
