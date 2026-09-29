<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoreRequest extends FormRequest
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
        /** @var Merchant $merchant */
        $merchant = $this->route('merchant');

        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', Rule::unique('stores', 'code')->where('merchant_id', $merchant->id)],
            'timezone' => ['required', 'timezone:all'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => str($this->input('code', ''))->trim()->upper()->toString()]);
    }
}
