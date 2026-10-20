<?php

namespace Features\Webhooks\CreateWebhook\Application;

use Features\Webhooks\Shared\Support\WebhookRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return WebhookRules::rules(creating: true);
    }
}
