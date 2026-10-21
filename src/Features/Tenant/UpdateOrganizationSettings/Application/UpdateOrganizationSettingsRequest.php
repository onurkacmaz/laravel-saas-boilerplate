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
        $rules = [
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'string', 'in:en,tr,de,fr,es'],
            'date_format' => ['sometimes', 'string', 'in:Y-m-d,d/m/Y,m/d/Y,d.m.Y'],
            'time_format' => ['sometimes', 'string', 'in:H:i:s,h:i:s A,H:i'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'notification_preferences' => ['sometimes', 'array:email,push,sms'],
            'notification_preferences.email' => ['sometimes', 'boolean'],
            'notification_preferences.push' => ['sometimes', 'boolean'],
            'notification_preferences.sms' => ['sometimes', 'boolean'],
        ];

        // The allowed flags are configurable (config/settings.php).
        $flags = array_values(array_filter((array) config('settings.feature_flags', []), 'is_string'));

        $rules['feature_flags'] = ['sometimes', 'array:'.implode(',', $flags)];

        foreach ($flags as $flag) {
            $rules["feature_flags.{$flag}"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
