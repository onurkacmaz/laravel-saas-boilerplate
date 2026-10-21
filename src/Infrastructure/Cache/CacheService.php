<?php

namespace Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache durations (in seconds)
     */
    const int DURATION_SHORT = 300;      // 5 minutes

    const int DURATION_MEDIUM = 1800;    // 30 minutes

    const int DURATION_LONG = 3600;      // 1 hour

    const int DURATION_DAY = 86400;      // 24 hours

    /**
     * Remember data in the cache with automatic key generation
     */
    public static function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return Cache::remember(
            self::generateKey($key),
            $ttl ?? self::DURATION_MEDIUM,
            $callback
        );
    }

    /**
     * Cache organization data
     */
    public static function rememberOrganization(string $organizationId, callable $callback): mixed
    {
        return self::remember(
            "organization:$organizationId",
            $callback,
            self::DURATION_LONG
        );
    }

    /**
     * Cache user data
     */
    public static function rememberUser(string $userId, callable $callback): mixed
    {
        return self::remember(
            "user:$userId",
            $callback,
            self::DURATION_LONG
        );
    }

    /**
     * Cache plan data
     */
    public static function rememberPlan(string $planId, callable $callback): mixed
    {
        return self::remember(
            "plan:$planId",
            $callback,
            self::DURATION_DAY
        );
    }

    /**
     * Cache subscription data
     */
    public static function rememberSubscription(string $organizationId, callable $callback): mixed
    {
        return self::remember(
            "subscription:$organizationId",
            $callback,
            self::DURATION_MEDIUM
        );
    }

    /**
     * Forget a value stored with remember().
     */
    public static function forget(string $key): bool
    {
        return Cache::forget(self::generateKey($key));
    }

    /**
     * Clear organization cache
     */
    public static function clearOrganization(string $organizationId): void
    {
        Cache::forget(self::generateKey("organization:$organizationId"));
        Cache::forget(self::generateKey("subscription:$organizationId"));
    }

    /**
     * Clear user cache
     */
    public static function clearUser(string $userId): void
    {
        Cache::forget(self::generateKey("user:$userId"));
    }

    /**
     * Clear plan cache
     */
    public static function clearPlan(string $planId): void
    {
        Cache::forget(self::generateKey("plan:$planId"));
    }

    /**
     * Clear subscription cache
     */
    public static function clearSubscription(string $organizationId): void
    {
        Cache::forget(self::generateKey("subscription:$organizationId"));
    }

    /**
     * Generate a cache key with a prefix
     */
    private static function generateKey(string $key): string
    {
        $prefix = config('cache.prefix', 'saas');

        return "$prefix:$key";
    }

    /**
     * Clear all cache for a specific tag
     */
    public static function clearTag(string $tag): void
    {
        if (config('cache.default') === 'redis') {
            Cache::tags($tag)->flush();
        }
    }

    /**
     * Cache data with tags (only works with Redis)
     */
    public static function rememberWithTags(array $tags, string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (config('cache.default') === 'redis') {
            return Cache::tags($tags)->remember(
                self::generateKey($key),
                $ttl ?? self::DURATION_MEDIUM,
                $callback
            );
        }

        return self::remember($key, $callback, $ttl);
    }
}
