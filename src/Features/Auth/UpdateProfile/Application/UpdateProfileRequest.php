<?php

namespace Features\Auth\UpdateProfile\Application;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * E-mails are stored lower-cased, so they must be validated (unique rule)
     * in the same form.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => User::normalizeEmail($this->input('email'))]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'locale' => ['sometimes', 'required', 'string', Rule::in(config('app.supported_locales'))],
            // Taking over a session must not be enough to take over the account.
            'current_password' => [Rule::requiredIf(fn () => $this->changesEmail()), 'nullable', 'string', 'current_password:sanctum'],
        ];
    }

    public function changesEmail(): bool
    {
        /** @var User $user */
        $user = $this->user();

        return $this->has('email') && $this->input('email') !== $user->email;
    }
}
