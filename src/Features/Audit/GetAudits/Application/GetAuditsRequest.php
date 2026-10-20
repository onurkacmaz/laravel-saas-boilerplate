<?php

namespace Features\Audit\GetAudits\Application;

use Illuminate\Foundation\Http\FormRequest;

class GetAuditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'event' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_id' => ['sometimes', 'nullable', 'uuid'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
