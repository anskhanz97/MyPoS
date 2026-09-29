<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreScaleBarcodeFormatRequest extends FormRequest
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
            'scope' => ['required', 'in:merchant,store'], 'name' => ['required', 'string', 'max:120'], 'prefix' => ['required', 'digits_between:1,6'],
            'total_length' => ['required', 'integer', 'min:8', 'max:32'], 'item_start' => ['required', 'integer', 'min:0', 'max:31'],
            'item_length' => ['required', 'integer', 'min:1', 'max:20'], 'value_start' => ['required', 'integer', 'min:0', 'max:31'],
            'value_length' => ['required', 'integer', 'min:1', 'max:20'], 'value_type' => ['required', 'in:quantity,price'],
            'value_decimal_places' => ['required', 'integer', 'min:0', 'max:6'], 'checksum' => ['required', 'in:none,ean13'],
        ];
    }
}
