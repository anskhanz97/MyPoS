<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:160'], 'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:254'], 'address_line' => ['sometimes', 'nullable', 'string', 'max:240'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'], 'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'customer_group_id' => ['sometimes', 'nullable', 'uuid'], 'email_receipt_preference' => ['sometimes', 'boolean'],
            'marketing_email_consent' => ['sometimes', 'boolean'], 'marketing_consent_source' => ['nullable', 'string', 'max:80'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }
}
