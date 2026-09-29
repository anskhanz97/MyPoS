<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreatePrintJobRequest extends FormRequest
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
        return ['idempotency_key' => ['required', 'uuid'], 'register_id' => ['required', 'uuid'], 'copy_type' => ['required', 'in:original,reprint'], 'reason' => ['nullable', 'string', 'max:500', 'required_if:copy_type,reprint'], 'printer_name' => ['nullable', 'string', 'max:160']];
    }
}
