<?php

namespace Features\Webhooks\Shared\Rules;

use Closure;
use Features\Webhooks\Shared\Support\WebhookUrlGuard;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! WebhookUrlGuard::isAllowed($value)) {
            $fail('The :attribute must be a public http(s) URL.');
        }
    }
}
