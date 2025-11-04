<?php

namespace Infrastructure\RateLimiting;

use Features\Subscription\Infrastructure\Eloquent\Models\Plan;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Http\Request;
use Infrastructure\Tenant\TenantContext;

readonly class RateLimiter
{
    public function __construct(
        private CacheRateLimiter $limiter,
        private TenantContext $tenantContext
    ) {}

    /**
     * Get the rate limit configuration for the current plan.
     */
    public function getPlanRateLimit(): array
    {
        $organization = $this->tenantContext->getOrganization();

        if (! $organization) {
            return $this->getDefaultRateLimit();
        }

        $subscription = $organization->activeSubscription();

        if (! $subscription) {
            return $this->getFreeRateLimit();
        }

        return $this->getRateLimitForPlan($subscription->plan);
    }

    /**
     * Get rate limit configuration for a specific plan.
     */
    private function getRateLimitForPlan(Plan $plan): array
    {
        $maxAttempts = $plan->limits['requests_per_minute'] ?? null;

        if (is_null($maxAttempts)) {
            return $this->getDefaultRateLimit();
        }

        // A value of -1 is considered unlimited. The underlying Laravel rate limiter handles this correctly.
        if ($maxAttempts < 0) {
            return [
                'max_attempts' => -1,
                'decay_minutes' => 1,
                'description' => 'Unlimited requests per minute',
            ];
        }

        return [
            'max_attempts' => $maxAttempts,
            'decay_minutes' => 1,
            'description' => "$maxAttempts requests per minute",
        ];
    }

    /**
     * Get default rate limit for unauthenticated requests.
     */
    private function getDefaultRateLimit(): array
    {
        return [
            'max_attempts' => 30,
            'decay_minutes' => 1,
            'description' => '30 requests per minute',
        ];
    }

    /**
     * Get free tier rate limit.
     */
    private function getFreeRateLimit(): array
    {
        return [
            'max_attempts' => 60,
            'decay_minutes' => 1,
            'description' => '60 requests per minute',
        ];
    }

    /**
     * Attempt to execute a callback with rate limiting.
     */
    public function attempt(string $key, callable $callback, ?array $limits = null): mixed
    {
        $limits = $limits ?? $this->getPlanRateLimit();

        return $this->limiter->attempt(
            $key,
            $limits['max_attempts'],
            $callback,
            $limits['decay_minutes'] * 60
        );
    }

    /**
     * Check if too many attempts have been made.
     */
    public function tooManyAttempts(string $key, ?array $limits = null): bool
    {
        $limits = $limits ?? $this->getPlanRateLimit();

        return $this->limiter->tooManyAttempts(
            $key,
            $limits['max_attempts']
        );
    }

    /**
     * Increment the counter for a given key.
     */
    public function hit(string $key, ?array $limits = null): int
    {
        $limits = $limits ?? $this->getPlanRateLimit();

        return $this->limiter->hit(
            $key,
            $limits['decay_minutes'] * 60
        );
    }

    /**
     * Get the number of attempts for a given key.
     */
    public function attempts(string $key): int
    {
        return $this->limiter->attempts($key);
    }

    /**
     * Get the number of retries left for a given key.
     */
    public function retriesLeft(string $key, ?array $limits = null): int
    {
        $limits = $limits ?? $this->getPlanRateLimit();

        return $this->limiter->retriesLeft(
            $key,
            $limits['max_attempts']
        );
    }

    /**
     * Clear the attempts for a given key.
     */
    public function clear(string $key): void
    {
        $this->limiter->clear($key);
    }

    /**
     * Get the number of seconds until the rate limit resets.
     */
    public function availableIn(string $key): int
    {
        return $this->limiter->availableIn($key);
    }

    /**
     * Generate a rate limit key for a request.
     */
    public function generateKey(Request $request, ?string $suffix = null): string
    {
        $organization = $this->tenantContext->getOrganization();
        $user = $request->user();

        $parts = [
            'rate-limit',
            $organization?->id ?? 'guest',
            $user?->id ?? $request->ip(),
        ];

        if ($suffix) {
            $parts[] = $suffix;
        }

        return implode(':', $parts);
    }
}
