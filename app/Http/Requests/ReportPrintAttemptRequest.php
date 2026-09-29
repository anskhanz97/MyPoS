<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportPrintAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['device_id' => ['required', 'uuid'], 'helper_event_id' => ['required', 'uuid'], 'attempt_number' => ['required', 'integer', 'min:1'], 'outcome' => ['required', 'in:accepted_by_helper,printed_reported,failed,outcome_unknown'], 'helper_id' => ['required', 'string', 'max:160'], 'helper_version' => ['required', 'string', 'max:40'], 'printer_name' => ['required', 'string', 'max:160'], 'error_code' => ['nullable', 'string', 'max:80'], 'safe_message' => ['nullable', 'string', 'max:500'], 'reported_at' => ['required', 'date']];
    }
}
