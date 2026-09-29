<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordStocktakeCountsRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'], 'lines' => ['required', 'array', 'min:1', 'max:500'], 'lines.*.stocktake_line_id' => ['required', 'uuid', 'distinct'],
            'lines.*.counted_quantity_base' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,6})?$/'],
        ];
    }
}
