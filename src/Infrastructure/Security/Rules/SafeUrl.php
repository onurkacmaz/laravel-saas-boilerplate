<?php

namespace Infrastructure\Security\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Infrastructure\Security\Support\UrlGuard;

/**
 * A public http(s) URL, safe to request from the server (see UrlGuard).
 */
class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! UrlGuard::isAllowed($value)) {
            $fail('The :attribute must be a public http(s) URL.');
        }
    }
}
