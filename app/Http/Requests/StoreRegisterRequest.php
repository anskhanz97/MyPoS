<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use App\Models\Store;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegisterRequest extends FormRequest
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
        $merchant = $this->route('merchant');
        $store = $this->route('store');

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:40', 'alpha_dash:ascii',
                Rule::unique('registers', 'code')->where(
                    fn ($query) => $query
                        ->where('merchant_id', $merchant instanceof Merchant ? $merchant->id : 0)
                        ->where('store_id', $store instanceof Store ? $store->id : 0),
                ),
            ],
            'capabilities' => ['required', 'array:scanner,printer,cash_drawer,customer_display'],
            'capabilities.scanner' => ['required', 'boolean'], 'capabilities.printer' => ['required', 'boolean'],
            'capabilities.cash_drawer' => ['required', 'boolean'], 'capabilities.customer_display' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => str($this->input('code', ''))->trim()->upper()->toString()]);
    }
}
