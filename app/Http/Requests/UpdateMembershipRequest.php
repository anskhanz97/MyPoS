<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\Enums\MembershipRole;
use App\Domain\Tenancy\Enums\MembershipStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMembershipRequest extends FormRequest
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
            'role' => ['sometimes', Rule::in([MembershipRole::Manager->value, MembershipRole::Cashier->value])],
            'status' => ['sometimes', Rule::enum(MembershipStatus::class)],
            'pin' => ['sometimes', 'digits_between:4,8'],
            'store_ids' => ['sometimes', 'array', 'min:1'],
            'store_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(['role', 'status', 'pin', 'store_ids'])) {
                $validator->errors()->add('membership', 'At least one membership change is required.');
            }
        }];
    }
}
