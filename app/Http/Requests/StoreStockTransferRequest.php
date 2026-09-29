<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
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
            'destination_store_id' => ['required', 'uuid'], 'source_location_id' => ['required', 'uuid'], 'destination_location_id' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:500'], 'lines.*.source_variant_id' => ['required', 'uuid'], 'lines.*.source_batch_id' => ['nullable', 'uuid'],
            'lines.*.quantity_base' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/', 'not_in:0,0.0,0.00,0.000,0.0000,0.00000,0.000000'],
        ];
    }
}
