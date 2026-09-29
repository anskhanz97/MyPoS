<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryAdjustmentRequest extends FormRequest
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
            'location_id' => ['required', 'uuid'],
            'variant_id' => ['required', 'uuid'],
            'batch_id' => ['nullable', 'uuid'],
            'unit_id' => ['required', 'uuid'],
            'quantity' => ['required', 'string', 'regex:/^-?(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/'],
            'reason_id' => ['required', 'uuid'],
            'reason_text' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
