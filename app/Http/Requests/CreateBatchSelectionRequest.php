<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateBatchSelectionRequest extends FormRequest
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
            'location_id' => ['required', 'uuid'],
            'variant_id' => ['required', 'uuid'],
            'quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/', 'not_in:0,0.0,0.00,0.000,0.0000,0.00000,0.000000'],
            'strategy' => ['required', 'in:fefo,manual'],
            'manual_allocations' => ['required_if:strategy,manual', 'array', 'max:100'],
            'manual_allocations.*.batch_id' => ['required', 'uuid', 'distinct'],
            'manual_allocations.*.quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/', 'not_in:0,0.0,0.00,0.000,0.0000,0.00000,0.000000'],
        ];
    }
}
