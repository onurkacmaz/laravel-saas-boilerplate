<?php

namespace Features\Webhooks\Shared\Support;

use Infrastructure\Security\Support\UrlGuard;

/**
 * @deprecated Use Infrastructure\Security\Support\UrlGuard.
 */
final class WebhookUrlGuard
{
    public static function isAllowed(string $url): bool
    {
        return UrlGuard::isAllowed($url);
    }

    public static function isPublicIp(string $ip): bool
    {
        return UrlGuard::isPublicIp($ip);
    }
}
