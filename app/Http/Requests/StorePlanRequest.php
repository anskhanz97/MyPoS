<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', 'unique:plans,code'],
            'name' => ['required', 'string', 'max:120'],
            'billing_period_days' => ['required', 'integer', 'min:1', 'max:366'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'price' => ['required', 'decimal:0,2', 'min:0'],
            'entitlements' => ['required', 'array', 'min:1'],
            'entitlements.*.code' => ['required', 'string', 'max:80', 'distinct'],
            'entitlements.*.enabled' => ['required', 'boolean'],
            'entitlements.*.limit_value' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
