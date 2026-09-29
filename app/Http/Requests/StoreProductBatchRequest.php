<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductBatchRequest extends FormRequest
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
            'variant_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:100'],
            'expiry_date' => ['required', 'date_format:Y-m-d'],
            'received_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:expiry_date'],
            'supplier_reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
