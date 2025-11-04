<?php

namespace Features\Subscription\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Infrastructure\RateLimiting\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class PlanBasedRateLimitMiddleware
{
    public function __construct(
        private RateLimiter $rateLimiter
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $key = $this->rateLimiter->generateKey($request, $scope);
        $limits = $this->rateLimiter->getPlanRateLimit();

        if ($this->rateLimiter->tooManyAttempts($key, $limits)) {
            return $this->buildRateLimitResponse($key, $limits);
        }

        $this->rateLimiter->hit($key, $limits);

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $key, $limits);
    }

    /**
     * Build a rate limit exceeded response.
     */
    private function buildRateLimitResponse(string $key, array $limits): Response
    {
        $retryAfter = $this->rateLimiter->availableIn($key);

        return response()->json([
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
            'limit' => $limits['max_attempts'],
            'description' => $limits['description'],
        ], 429)->withHeaders([
            'X-RateLimit-Limit' => $limits['max_attempts'],
            'X-RateLimit-Remaining' => 0,
            'Retry-After' => $retryAfter,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);
    }

    /**
     * Add rate limit headers to the response.
     */
    private function addRateLimitHeaders(Response $response, string $key, array $limits): Response
    {
        $remaining = $this->rateLimiter->retriesLeft($key, $limits);
        $retryAfter = $this->rateLimiter->availableIn($key);

        $response->headers->add([
            'X-RateLimit-Limit' => $limits['max_attempts'],
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);

        return $response;
    }
}
