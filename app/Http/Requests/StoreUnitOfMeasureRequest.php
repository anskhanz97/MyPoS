<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitOfMeasureRequest extends FormRequest
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
        return ['code' => ['required', 'string', 'max:24', 'alpha_dash:ascii'], 'name' => ['required', 'string', 'max:80'], 'symbol' => ['required', 'string', 'max:16'], 'decimal_places' => ['required', 'integer', 'min:0', 'max:6']];
    }
}
