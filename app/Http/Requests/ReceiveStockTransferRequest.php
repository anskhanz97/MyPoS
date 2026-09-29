<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockTransferRequest extends FormRequest
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
            'idempotency_key' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'],
            'resolution' => ['required', 'in:partial,complete,short_close,excess_accept'], 'reason' => ['required_if:resolution,short_close,excess_accept', 'nullable', 'string', 'min:3', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:500'], 'lines.*.transfer_line_id' => ['required', 'uuid', 'distinct'],
            'lines.*.quantity_destination_base' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/'],
        ];
    }
}
