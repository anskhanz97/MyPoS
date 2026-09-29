<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OfflineBootstrapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'register_id' => ['required', 'uuid'],
            'supported_schema_versions' => ['required', 'array', 'min:1', 'max:10'],
            'supported_schema_versions.*' => ['required', 'integer', 'distinct', 'min:1', 'max:1'],
        ];
    }
}
