<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function validationData(): array
    {
        return [...parent::validationData(), 'idempotency_key' => $this->header('Idempotency-Key')];
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], 'return' => ['required', 'array'], 'return.idempotency_key' => ['required', 'uuid'], 'return.register_session_id' => ['required', 'uuid'], 'return.reason' => ['required', 'string', 'min:3', 'max:500'], 'return.late_reason' => ['nullable', 'string', 'min:3', 'max:500'],
            'return.lines' => ['required', 'array', 'min:1', 'max:250'], 'return.lines.*.sale_line_id' => ['required', 'uuid', 'distinct'], 'return.lines.*.quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,6})?$/'], 'return.lines.*.disposition' => ['required', 'in:restock,damaged,expired,discard'],
            'return.refund_tenders' => ['required', 'array', 'min:1', 'max:10'], 'return.refund_tenders.*.tender_id' => ['required', 'uuid', 'distinct'], 'return.refund_tenders.*.type' => ['required', 'in:cash,external_card,external_wallet,bank_transfer'], 'return.refund_tenders.*.state' => ['required', 'in:refunded,pending_external_action'], 'return.refund_tenders.*.amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'], 'return.refund_tenders.*.external_reference' => ['nullable', 'string', 'max:200'], 'return.refund_tenders.*.confirmation_source' => ['nullable', 'in:staff_declaration,not_applicable'],
            'new_sale' => ['required', 'array'], 'new_sale.idempotency_key' => ['required', 'uuid'], 'new_sale.client_transaction_id' => ['required', 'uuid'], 'new_sale.quote_id' => ['required', 'uuid'], 'new_sale.quote_version' => ['required', 'integer', 'min:1'], 'new_sale.register_session_id' => ['required', 'uuid'],
            'new_sale.tenders' => ['required', 'array', 'min:1', 'max:10'], 'new_sale.tenders.*.tender_id' => ['required', 'uuid', 'distinct'], 'new_sale.tenders.*.type' => ['required', 'in:cash,external_card,external_wallet,bank_transfer'], 'new_sale.tenders.*.amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'], 'new_sale.tenders.*.external_reference' => ['nullable', 'string', 'max:200'], 'new_sale.tenders.*.confirmation_source' => ['required', 'in:staff_declaration,not_applicable'], 'new_sale.cash_change' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
        ];
    }
}
