<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeSaleRequest extends FormRequest
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
            'idempotency_key' => ['required', 'uuid'], 'client_transaction_id' => ['required', 'uuid'], 'quote_id' => ['required', 'uuid'], 'quote_version' => ['required', 'integer', 'min:1'], 'register_session_id' => ['required', 'uuid'],
            'tenders' => ['required', 'array', 'min:1', 'max:10'], 'tenders.*.tender_id' => ['required', 'uuid', 'distinct'], 'tenders.*.type' => ['required', 'in:cash,external_card,external_wallet,bank_transfer'],
            'tenders.*.amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'], 'tenders.*.external_reference' => ['nullable', 'string', 'max:200'], 'tenders.*.confirmation_source' => ['required', 'in:staff_declaration,not_applicable'],
            'cash_change' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,2})?$/'],
        ];
    }
}
