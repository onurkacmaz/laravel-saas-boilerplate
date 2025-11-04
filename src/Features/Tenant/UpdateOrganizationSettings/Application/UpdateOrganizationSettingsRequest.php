<?php

namespace Features\Tenant\UpdateOrganizationSettings\Application;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationSettingsRequest extends FormRequest
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
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'string', 'in:en,tr,de,fr,es'],
            'date_format' => ['sometimes', 'string', 'in:Y-m-d,d/m/Y,m/d/Y,d.m.Y'],
            'time_format' => ['sometimes', 'string', 'in:H:i:s,h:i:s A,H:i'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.email' => ['sometimes', 'boolean'],
            'notification_preferences.push' => ['sometimes', 'boolean'],
            'notification_preferences.sms' => ['sometimes', 'boolean'],
            'feature_flags' => ['sometimes', 'array'],
            'feature_flags.advanced_analytics' => ['sometimes', 'boolean'],
            'feature_flags.api_access' => ['sometimes', 'boolean'],
            'feature_flags.custom_branding' => ['sometimes', 'boolean'],
        ];
    }
}
