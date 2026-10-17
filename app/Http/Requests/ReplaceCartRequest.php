<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'change_reason' => ['nullable', 'string', 'max:500'],
            'expected_version' => ['required', 'integer', 'min:1'], 'customer_id' => ['nullable', 'uuid'],
            'coupon_codes' => ['sometimes', 'array', 'max:20'], 'coupon_codes.*' => ['required', 'string', 'max:80', 'distinct'],
            'manual_cart_discount' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'lines' => ['required', 'array', 'max:250'], 'lines.*.variant_id' => ['required', 'uuid'], 'lines.*.unit_id' => ['nullable', 'uuid'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,6})?$/'],
            'lines.*.manual_discount' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'lines.*.batch_selection_id' => ['nullable', 'uuid'], 'lines.*.extra_option_ids' => ['sometimes', 'array', 'max:30'], 'lines.*.extra_option_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
