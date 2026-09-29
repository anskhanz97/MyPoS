<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishSettingsRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:0'], 'effective_at' => ['nullable', 'date'],
            'payload' => ['required', 'array:timezone,business_day_cutoff,promotion_mode,features,hardware,returns'],
            'payload.timezone' => ['sometimes', 'timezone:all'], 'payload.business_day_cutoff' => ['sometimes', 'date_format:H:i'],
            'payload.promotion_mode' => ['sometimes', 'in:manual,advanced'],
            'payload.features' => ['sometimes', 'array:customer_display'], 'payload.features.customer_display' => ['sometimes', 'boolean'],
            'payload.hardware' => ['sometimes', 'array:scanner,printer,cash_drawer'],
            'payload.hardware.scanner' => ['sometimes', 'array:mode'], 'payload.hardware.scanner.mode' => ['sometimes', 'in:keyboard_wedge'],
            'payload.hardware.printer' => ['sometimes', 'array:transport,profile'], 'payload.hardware.printer.transport' => ['sometimes', 'in:none,browser_dialog,local_helper'], 'payload.hardware.printer.profile' => ['nullable', 'string', 'max:120'],
            'payload.hardware.cash_drawer' => ['sometimes', 'array:enabled'], 'payload.hardware.cash_drawer.enabled' => ['sometimes', 'boolean'],
            'payload.returns' => ['sometimes', 'array:window_days'], 'payload.returns.window_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ];
    }
}
