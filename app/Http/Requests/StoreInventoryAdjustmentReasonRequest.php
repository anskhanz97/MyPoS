<?php

namespace App\Http\Requests;

use App\Domain\Inventory\Enums\AdjustmentDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAdjustmentReasonRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'direction' => ['required', Rule::enum(AdjustmentDirection::class)],
            'requires_approval' => ['sometimes', 'boolean'],
        ];
    }
}
