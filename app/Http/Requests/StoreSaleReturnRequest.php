<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
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

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], 'register_session_id' => ['required', 'uuid'], 'reason' => ['required', 'string', 'min:3', 'max:500'], 'late_reason' => ['nullable', 'string', 'min:3', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:250'], 'lines.*.sale_line_id' => ['required', 'uuid', 'distinct'], 'lines.*.quantity' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,6})?$/'], 'lines.*.disposition' => ['required', 'in:restock,damaged,expired,discard'],
            'refund_tenders' => ['required', 'array', 'min:1', 'max:10'], 'refund_tenders.*.tender_id' => ['required', 'uuid', 'distinct'], 'refund_tenders.*.type' => ['required', 'in:cash,external_card,external_wallet,bank_transfer'],
            'refund_tenders.*.state' => ['required', 'in:refunded,pending_external_action'], 'refund_tenders.*.amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
            'refund_tenders.*.external_reference' => ['nullable', 'string', 'max:200'], 'refund_tenders.*.confirmation_source' => ['nullable', 'in:staff_declaration,not_applicable'],
        ];
    }
}
