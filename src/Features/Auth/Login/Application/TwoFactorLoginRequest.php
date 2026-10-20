<?php

namespace Features\Auth\Login\Application;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TwoFactorLoginRequest extends FormRequest
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
            'jwt' => ['required', 'string', 'max:4096'],
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:64'],
        ];
    }
}
