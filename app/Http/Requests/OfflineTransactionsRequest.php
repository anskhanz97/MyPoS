<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OfflineTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'register_id' => ['required', 'uuid'],
            'commands' => ['required', 'array', 'min:1', 'max:100'],
            'commands.*.command_id' => ['required', 'uuid', 'distinct'],
            'commands.*.command_type' => ['required', 'in:finalize_cash_sale,cash_movement'],
            'commands.*.schema_version' => ['required', 'integer', 'in:1'],
            'commands.*.committed_at_local' => ['required', 'date'],
            'commands.*.snapshot_version' => ['required', 'string', 'max:160'],
            'commands.*.fencing_epoch' => ['required', 'integer', 'min:1'],
            'commands.*.payload_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'commands.*.payload' => ['required', 'array'],
            'commands.*.payload.membership_id' => ['required', 'uuid'],
            'commands.*.payload.register_session_id' => ['required', 'uuid'],
            'commands.*.payload.inventory_location_id' => ['sometimes', 'uuid'],
            'commands.*.payload.client_transaction_id' => ['sometimes', 'uuid'],
            'commands.*.payload.cash_tender_id' => ['sometimes', 'uuid'],
            'commands.*.payload.cash_received' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'commands.*.payload.cash_change' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'commands.*.payload.expected_total' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'commands.*.payload.cart' => ['sometimes', 'array'],
            'commands.*.payload.cart.customer_id' => ['sometimes', 'nullable', 'uuid'],
            'commands.*.payload.cart.coupon_codes' => ['sometimes', 'array', 'max:20'],
            'commands.*.payload.cart.coupon_codes.*' => ['required', 'string', 'max:80', 'distinct'],
            'commands.*.payload.cart.manual_cart_discount' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'commands.*.payload.cart.lines' => ['sometimes', 'array', 'min:1', 'max:250'],
            'commands.*.payload.cart.lines.*.variant_id' => ['required_with:commands.*.payload.cart.lines', 'uuid'],
            'commands.*.payload.cart.lines.*.unit_id' => ['sometimes', 'nullable', 'uuid'],
            'commands.*.payload.cart.lines.*.quantity' => ['required_with:commands.*.payload.cart.lines', 'string', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,6})?$/'],
            'commands.*.payload.cart.lines.*.manual_discount' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'commands.*.payload.cart.lines.*.batch_selection_id' => ['sometimes', 'nullable', 'uuid'],
            'commands.*.payload.cart.lines.*.extra_option_ids' => ['sometimes', 'array', 'max:30'],
            'commands.*.payload.cart.lines.*.extra_option_ids.*' => ['required', 'uuid', 'distinct'],
            'commands.*.payload.kind' => ['sometimes', 'in:paid_in,paid_out,no_sale_open'],
            'commands.*.payload.amount' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'commands.*.payload.reason' => ['sometimes', 'string', 'max:500'],
            'commands.*.payload.occurred_at' => ['sometimes', 'date'],
        ];
    }
}
