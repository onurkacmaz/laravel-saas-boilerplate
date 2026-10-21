<?php

namespace Infrastructure\Security\Support;

/**
 * Protects outgoing requests to user-supplied URLs (webhooks, monitors...)
 * against Server-Side Request Forgery: URLs must use http(s) and must not
 * resolve to private or reserved network ranges (cloud metadata services,
 * localhost, internal services...).
 */
final class UrlGuard
{
    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        // Credentials embedded in the URL would be sent to the endpoint and
        // stored in plain text; refuse them.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        // `webhooks.allow_private_urls` is the former name of the setting.
        if (config('security.allow_private_urls', false) || config('webhooks.allow_private_urls', false)) {
            return true;
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        // Resolve the host and make sure none of its addresses is private. A
        // host that does not resolve is let through: the request will fail on
        // its own and is recorded as a failed delivery.
        foreach (self::resolve($host) as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false && ! self::isSpecialPurpose($ip);
    }

    /**
     * Ranges not covered by PHP's NO_PRIV/NO_RES flags.
     */
    private static function isSpecialPurpose(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);

            foreach (['100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15', '0.0.0.0/8'] as $cidr) {
                [$subnet, $bits] = explode('/', $cidr);
                $mask = -1 << (32 - (int) $bits);

                if (($long & $mask) === (ip2long($subnet) & $mask)) {
                    return true;
                }
            }

            return false;
        }

        // IPv4-mapped IPv6 addresses (::ffff:10.0.0.1) inherit the IPv4 checks.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $matches)) {
            return ! self::isPublicIp($matches[1]);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $ips = [];

        foreach ($records as $record) {
            if (! empty($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }
}
