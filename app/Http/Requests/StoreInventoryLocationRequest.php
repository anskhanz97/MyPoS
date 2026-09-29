<?php

namespace App\Http\Requests;

use App\Domain\Inventory\Enums\InventoryDisposition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryLocationRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'disposition' => ['required', Rule::enum(InventoryDisposition::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
