<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishCustomerDisplayProjectionRequest extends FormRequest
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
        return ['state' => ['required', 'in:idle,cart,suspended,cancelled,completed'], 'cart_id' => ['nullable', 'uuid', 'required_if:state,cart'], 'sale_id' => ['nullable', 'uuid', 'required_if:state,completed']];
    }
}
