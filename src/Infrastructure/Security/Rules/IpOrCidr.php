<?php

namespace Infrastructure\Security\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An IPv4 or IPv6 address, or a CIDR range, as accepted by `ip.whitelist`
 * and IpWhitelist::allows().
 */
class IpOrCidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('The :attribute must be an IP address or a CIDR range.');
        }
    }

    public static function isValid(string $value): bool
    {
        if (! str_contains($value, '/')) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false;
        }

        [$ip, $bits] = explode('/', $value, 2);

        if (! ctype_digit($bits)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return (int) $bits <= 32;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false && (int) $bits <= 128;
    }
}
