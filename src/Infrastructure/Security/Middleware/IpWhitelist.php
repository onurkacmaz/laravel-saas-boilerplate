<?php

namespace Infrastructure\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class IpWhitelist
{
    /**
     * Handle an incoming request.
     *
     * Usage: Add ->middleware('ip.whitelist:192.168.1.1,10.0.0.0/8') to the route.
     * Both single addresses and CIDR ranges (IPv4 and IPv6) are supported.
     * For lists stored with your data, use IpWhitelist::allows() instead.
     */
    public function handle(Request $request, Closure $next, string ...$allowedIps): Response
    {
        // If no whitelist is specified, allow all
        if (empty($allowedIps)) {
            return $next($request);
        }

        if (! self::allows($request->ip(), $allowedIps)) {
            return response()->json([
                'message' => 'Access denied. Your IP address is not whitelisted.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Whether the IP matches one of the addresses or CIDR ranges. An empty
     * list allows every IP; a missing IP is never allowed by a non-empty list.
     *
     * @param  array<int, string>  $allowedIps
     */
    public static function allows(?string $ip, array $allowedIps): bool
    {
        $allowedIps = array_values(array_filter(array_map('trim', $allowedIps), fn (string $value) => $value !== ''));

        if ($allowedIps === []) {
            return true;
        }

        return $ip !== null && $ip !== '' && IpUtils::checkIp($ip, $allowedIps);
    }
}
