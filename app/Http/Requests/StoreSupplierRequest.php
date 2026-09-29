<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/'], 'name' => ['required', 'string', 'min:1', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:160'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email:rfc', 'max:254'],
            'tax_reference' => ['nullable', 'string', 'max:80'], 'address_line_1' => ['nullable', 'string', 'max:200'], 'address_line_2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
