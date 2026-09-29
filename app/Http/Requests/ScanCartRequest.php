<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScanCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'code' => ['required', 'string', 'max:64', 'regex:/^[0-9A-Za-z-]+$/']];
    }
}
