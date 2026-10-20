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
     */
    public function handle(Request $request, Closure $next, string ...$allowedIps): Response
    {
        // If no whitelist is specified, allow all
        if (empty($allowedIps)) {
            return $next($request);
        }

        $requestIp = $request->ip();

        // Check if request IP is in the whitelist
        if (! $requestIp || ! IpUtils::checkIp($requestIp, array_map('trim', $allowedIps))) {
            return response()->json([
                'message' => 'Access denied. Your IP address is not whitelisted.',
            ], 403);
        }

        return $next($request);
    }
}
