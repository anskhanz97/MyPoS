<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceBundleComponentsRequest extends FormRequest
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
        return ['expected_structure_version' => ['required', 'integer', 'min:1'], 'components' => ['required', 'array', 'min:1', 'max:100'], 'components.*.variant_id' => ['required', 'uuid', 'distinct'], 'components.*.quantity_base' => ['required', 'decimal:1,6', 'gt:0']];
    }
}
