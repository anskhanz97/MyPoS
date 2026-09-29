<?php

namespace App\Http\Requests;

class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'expected_version' => ['required', 'integer', 'min:1'], 'amendment_reason' => ['nullable', 'string', 'min:3', 'max:500'],
            'lines.*.id' => ['nullable', 'uuid', 'distinct'],
        ];
    }
}
