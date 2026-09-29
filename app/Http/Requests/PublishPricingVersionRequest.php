<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishPricingVersionRequest extends FormRequest
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
            'scope' => ['required', 'in:merchant,store'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'mode' => ['required', 'in:manual,advanced'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'prices' => ['required', 'array', 'min:1', 'max:10000'],
            'prices.*.variant_id' => ['required', 'uuid'],
            'prices.*.customer_group' => ['nullable', 'string', 'max:80'],
            'prices.*.unit_price' => ['required', 'decimal:0,4', 'min:0', 'max:999999999999.9999'],
            'prices.*.tax_rate_percent' => ['sometimes', 'decimal:0,6', 'min:0', 'max:100'],
            'prices.*.tax_inclusive' => ['sometimes', 'boolean'],
            'rules' => ['sometimes', 'array', 'max:1000'],
            'rules.*.type' => ['required', 'in:scheduled_price,coupon,quantity_discount,mix_and_match,buy_x_get_y'],
            'rules.*.name' => ['required', 'string', 'max:160'],
            'rules.*.code' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'rules.*.priority' => ['required', 'integer', 'min:1', 'max:100000'],
            'rules.*.stackable' => ['required', 'boolean'],
            'rules.*.exclusive_group' => ['nullable', 'string', 'max:80'],
            'rules.*.starts_at' => ['nullable', 'date'],
            'rules.*.ends_at' => ['nullable', 'date'],
            'rules.*.conditions' => ['present', 'array'],
            'rules.*.reward' => ['required', 'array'],
            'rules.*.maximum_redemptions' => ['nullable', 'integer', 'min:1'],
            'rules.*.maximum_per_customer' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
