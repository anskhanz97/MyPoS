<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSaleReturnTenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['external_reference' => ['required', 'string', 'max:200'], 'confirmation_source' => ['required', 'in:staff_declaration']];
    }
}
