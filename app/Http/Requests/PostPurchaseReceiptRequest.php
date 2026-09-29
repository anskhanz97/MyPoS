<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostPurchaseReceiptRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'location_id' => ['required', 'uuid'],
            'received_at' => ['required', 'date'],
            'supplier_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/', 'not_in:0,0.0,0.00,0.000,0.0000,0.00000,0.000000'],
            'lines.*.unit_cost' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/'],
            'lines.*.landed_cost_total' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/'],
            'lines.*.recoverable_tax_total' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/'],
            'lines.*.batch_code' => ['nullable', 'string', 'max:100'],
            'lines.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
