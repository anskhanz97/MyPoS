<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportAccessGrantRequest extends FormRequest
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
        return ['merchant_id' => ['required', 'uuid'], 'scope' => ['required', 'in:diagnostics,export,account_recovery'], 'purpose' => ['required', 'string', 'min:10', 'max:1000'], 'expires_at' => ['required', 'date', 'after:now']];
    }
}
