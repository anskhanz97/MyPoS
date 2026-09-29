<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignSubscriptionRequest extends FormRequest
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
        return ['plan_id' => ['required', 'uuid'], 'status' => ['required', 'in:trial,active'], 'starts_at' => ['required', 'date'], 'trial_ends_at' => ['required_if:status,trial', 'nullable', 'date', 'after:starts_at']];
    }
}
