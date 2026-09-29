<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConvertProductToBatchTrackingRequest extends FormRequest
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
            'idempotency_key' => ['required', 'uuid'],
            'product_id' => ['required', 'uuid'],
            'expected_structure_version' => ['required', 'integer', 'min:1'],
            'allocations' => ['present', 'array', 'max:1000'],
            'allocations.*.location_id' => ['required', 'uuid'],
            'allocations.*.variant_id' => ['required', 'uuid'],
            'allocations.*.batch_code' => ['required', 'string', 'max:100'],
            'allocations.*.expiry_date' => ['required', 'date_format:Y-m-d'],
            'allocations.*.quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/', 'not_in:0,0.0,0.00,0.000,0.0000,0.00000,0.000000'],
        ];
    }
}
