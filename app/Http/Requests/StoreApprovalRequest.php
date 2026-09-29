<?php

namespace App\Http\Requests;

use App\Domain\Auth\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approver_membership_id' => ['required', 'uuid'],
            'approver_pin' => ['required', 'digits_between:4,8'],
            'permission' => ['required', Rule::enum(Permission::class)],
            'target_type' => ['required', 'string', 'max:80', 'alpha_dash:ascii'],
            'target_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
