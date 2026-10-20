<?php

namespace Features\Tenant\SendInvitation\Application;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SendInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => User::normalizeEmail($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'role_id.required' => 'Role is required.',
            'role_id.exists' => 'The selected role does not exist.',
        ];
    }
}
