<?php

namespace Features\Tenant\AddUserToOrganization\Application;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddUserToOrganizationRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
        ];
    }
}
