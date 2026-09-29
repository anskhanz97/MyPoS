<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\Enums\StoreDataMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash:ascii', Rule::unique('merchants', 'slug')],
            'store_data_mode' => ['required', Rule::enum(StoreDataMode::class)],
            'timezone' => ['required', 'timezone:all'],
            'store_name' => ['required', 'string', 'max:160'],
            'store_code' => ['required', 'string', 'max:40', 'alpha_dash:ascii'],
            'review_confirmed' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => str($this->input('slug', ''))->trim()->lower()->toString(),
            'store_code' => str($this->input('store_code', ''))->trim()->upper()->toString(),
        ]);
    }
}
