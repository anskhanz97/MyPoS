<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\Enums\MembershipRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipRequest extends FormRequest
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
            'email' => ['required', 'email:rfc', 'max:254'],
            'role' => ['required', Rule::in([MembershipRole::Manager->value, MembershipRole::Cashier->value])],
            'pin' => ['required', 'digits_between:4,8'],
            'store_ids' => ['required', 'array', 'min:1'],
            'store_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->string('email')->trim()->lower()->toString()]);
    }
}
