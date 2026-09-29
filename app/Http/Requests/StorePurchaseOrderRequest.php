<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid'], 'order_date' => ['required', 'date_format:Y-m-d'], 'expected_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'source_reference' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'], 'lines.*.variant_id' => ['required', 'uuid'], 'lines.*.purchase_unit_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/', 'not_in:0,0.0,0.00,0.000,0.0000,0.00000,0.000000'],
            'lines.*.expected_unit_cost' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/'], 'lines.*.tax_reference' => ['nullable', 'string', 'max:80'],
        ];
    }
}
