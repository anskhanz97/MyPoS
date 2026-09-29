<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogProductRequest extends FormRequest
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
            'expected_structure_version' => ['required', 'integer', 'min:1'], 'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'], 'tax_classification_reference' => ['sometimes', 'nullable', 'string', 'max:80'],
            'archived' => ['sometimes', 'boolean'], 'batch_tracking_enabled' => ['sometimes', 'boolean'],
            'batch_conversion_confirmed' => ['sometimes', 'accepted'], 'available_store_ids' => ['sometimes', 'array'], 'available_store_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
