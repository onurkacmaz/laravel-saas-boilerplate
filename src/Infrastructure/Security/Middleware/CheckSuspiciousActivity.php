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
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        // $userAgent = $request->userAgent();

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
        if ($this->hasUnusualRequestRate($ip)) {
            $this->logSuspiciousActivity($request, 'Unusual request rate detected');
            $this->incrementFailureCount($ip);

            return response()->json(['message' => 'Too many requests'], 429);
        }

        return $next($request);
    }

    protected function hasSuspiciousContent(Request $request): bool
    {
        $content = json_encode($request->all());

        return array_any($this->suspiciousPatterns, fn ($pattern) => preg_match($pattern, $content));
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
            Cache::put($key, 1, now()->addHour());
        }

        // Block IP of too many suspicious activities
        if ($count >= 5) {
            Cache::put("blocked_ip:$ip", true, now()->addDay());
            Log::warning("IP blocked due to suspicious activity: $ip");
        }
    }

    protected function hasUnusualRequestRate(string $ip): bool
    {
        $key = "request_rate:$ip";
        $requests = Cache::get($key, 0);

        Cache::put($key, $requests + 1, now()->addMinute());

        // More than 100 requests per minute is suspicious
        return $requests > 100;
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
