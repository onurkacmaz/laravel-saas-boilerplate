<?php

namespace Infrastructure\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckSuspiciousActivity
{
    protected array $suspiciousPatterns = [
        '/\.\.\//i',  // Directory traversal
        '/<script/i', // XSS attempts
        '/union.*select/i', // SQL injection
        '/drop.*table/i', // SQL injection
        '/exec\(/i', // Code execution
        '/eval\(/i', // Code execution
    ];

    /**
     * Handle an incoming request.
     *
     * Usage: ->middleware('security.check'), or ->middleware('security.check:600')
     * to allow a route more requests per minute (0 disables the rate check).
     */
    public function handle(Request $request, Closure $next, ?string $maxRequestsPerMinute = null): Response
    {
        $ip = $request->ip() ?? 'unknown';

        // Check for suspicious patterns in a request
        if ($this->hasSuspiciousContent($request)) {
            $this->logSuspiciousActivity($request, 'Suspicious pattern detected');
            $this->incrementFailureCount($ip);

            return response()->json(['message' => 'Invalid request'], 400);
        }

        // Check if IP is blocked
        if ($this->isBlocked($ip)) {
            $this->logSuspiciousActivity($request, 'Blocked IP attempted access');

            return response()->json(['message' => 'Access denied'], 403);
        }

        // Check for unusual request rate
        if ($this->hasUnusualRequestRate($ip, $maxRequestsPerMinute !== null ? (int) $maxRequestsPerMinute : null)) {
            $this->logSuspiciousActivity($request, 'Unusual request rate detected');
            $this->incrementFailureCount($ip);

            return response()->json(['message' => 'Too many requests'], 429);
        }

        return $next($request);
    }

    protected function hasSuspiciousContent(Request $request): bool
    {
        // Slashes must stay unescaped, otherwise "../" is encoded as "..\/" and the
        // directory traversal pattern never matches.
        $content = json_encode($request->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($content === false) {
            return false;
        }

        return array_any($this->suspiciousPatterns, fn ($pattern) => preg_match($pattern, $content) === 1);
    }

    protected function isBlocked(string $ip): bool
    {
        return Cache::has("blocked_ip:$ip");
    }

    protected function incrementFailureCount(string $ip): void
    {
        $key = "suspicious_activity:$ip";
        $count = Cache::increment($key);

        if ($count === 1) {
            Cache::put($key, 1, now()->addMinutes((int) config('security.suspicious_activity.violation_window_minutes', 60)));
        }

        // Block IP of too many suspicious activities
        if ($count >= (int) config('security.suspicious_activity.max_violations', 5)) {
            Cache::put("blocked_ip:$ip", true, now()->addMinutes((int) config('security.suspicious_activity.block_minutes', 1440)));
            Log::warning("IP blocked due to suspicious activity: $ip");
        }
    }

    protected function hasUnusualRequestRate(string $ip, ?int $maxRequestsPerMinute = null): bool
    {
        $maxRequestsPerMinute ??= (int) config('security.suspicious_activity.max_requests_per_minute', 100);

        if ($maxRequestsPerMinute <= 0) {
            return false;
        }

        $key = "request_rate:$ip";

        // Fixed one-minute window: the TTL is only set when the window starts, so a
        // steady trickle of requests can no longer keep the counter alive forever.
        Cache::add($key, 0, now()->addMinute());
        $requests = (int) Cache::increment($key);

        return $requests > $maxRequestsPerMinute;
    }

    protected function logSuspiciousActivity(Request $request, string $reason): void
    {
        Log::warning('Suspicious activity detected', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'input' => $request->except(['password', 'password_confirmation']),
        ]);
    }
}
