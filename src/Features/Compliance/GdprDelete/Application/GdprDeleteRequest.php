<?php

namespace Features\Compliance\GdprDelete\Application;

use Illuminate\Foundation\Http\FormRequest;

class GdprDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Immediate, irreversible deletion requires the current password.
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:sanctum'],
        ];
    }
}
