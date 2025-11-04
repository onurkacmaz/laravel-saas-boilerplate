<?php

namespace Features\Webhooks\UpdateWebhook\Application;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['sometimes', 'string'],
            'secret' => ['nullable', 'string', 'min:16', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'headers' => ['nullable', 'array'],
            'timeout' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'retry_limit' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ];
    }
}
