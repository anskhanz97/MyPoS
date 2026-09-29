<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'local_reference' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'address_line' => ['nullable', 'string', 'max:240'], 'city' => ['nullable', 'string', 'max:120'], 'postal_code' => ['nullable', 'string', 'max:20'],
            'customer_group_id' => ['nullable', 'uuid'],
            'email_receipt_preference' => ['sometimes', 'boolean'], 'marketing_email_consent' => ['sometimes', 'boolean'],
            'marketing_consent_source' => ['nullable', 'string', 'max:80'],
        ];
    }
}
