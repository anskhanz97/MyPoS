<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CalculatePricingQuoteRequest extends FormRequest
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
            'pricing_version' => ['nullable', 'string', 'max:40'],
            'customer_group' => ['nullable', 'string', 'max:80'],
            'customer_reference' => ['nullable', 'string', 'max:120'],
            'coupon_codes' => ['sometimes', 'array', 'max:10'],
            'coupon_codes.*' => ['string', 'max:80'],
            'manual_cart_discount' => ['nullable', 'decimal:0,2', 'min:0'],
            'lines' => ['required', 'array', 'min:1', 'max:250'],
            'lines.*.line_id' => ['required', 'uuid', 'distinct'],
            'lines.*.variant_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'lines.*.manual_discount' => ['nullable', 'decimal:0,2', 'min:0'],
        ];
    }
}
