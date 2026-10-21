<?php

namespace Features\Subscription\Shared\Support;

use App\Models\Organization;
use Infrastructure\Tenant\TenantContext;

class ResourceLimits
{
    /**
     * Limits registered by modules, used when a plan does not define them.
     *
     * @var array<string, mixed>
     */
    private static array $registeredDefaults = [];

    public function __construct(
        private ?Organization $organization = null
    ) {
        $this->organization = $organization ?? TenantContext::getOrganization();
    }

    /**
     * Register the limit used when an organization's plan does not define one
     * (or the organization has no subscription), e.g. for a module's own
     * resources. Without it, such a resource is unlimited. Register defaults
     * from a service provider's boot method; a negative limit means unlimited.
     */
    public static function registerDefault(string $key, mixed $limit): void
    {
        self::$registeredDefaults[$key] = $limit;
    }

    public static function forgetDefault(string $key): void
    {
        unset(self::$registeredDefaults[$key]);
    }

    /**
     * Check if the organization has reached a specific limit.
     */
    public function hasReached(string $key, int $current): bool
    {
        $limit = $this->getLimit($key);

        if (! is_numeric($limit) || (int) $limit < 0) {
            return false; // Unlimited
        }

        return $current >= (int) $limit;
    }

    /**
     * Check if the organization can perform an action based on limit.
     */
    public function canPerform(string $key, int $current): bool
    {
        return ! $this->hasReached($key, $current);
    }

    /**
     * Get the limit value for a specific key.
     */
    public function getLimit(string $key, mixed $default = null): mixed
    {
        if (! $this->organization) {
            return $default;
        }

        $subscription = $this->organization->activeSubscription();

        if (! $subscription) {
            return $this->getDefaultLimit($key, $default);
        }

        if (! $subscription->plan) {
            return $this->getDefaultLimit($key, $default);
        }

        return $subscription->plan->getLimit($key, $this->getDefaultLimit($key, $default));
    }

    /**
     * Get all limits for the current organization.
     */
    public function getAllLimits(): array
    {
        if (! $this->organization) {
            return $this->getDefaultLimits();
        }

        $subscription = $this->organization->activeSubscription();

        if (! $subscription) {
            return $this->getDefaultLimits();
        }

        $limits = $subscription->plan?->limits;

        if ($limits === null) {
            return $this->getDefaultLimits();
        }

        // Limits registered by modules count even when the plan omits them.
        return [...self::$registeredDefaults, ...$limits];
    }

    /**
     * Get default limits (free tier).
     */
    private function getDefaultLimits(): array
    {
        return [
            'users' => 5,
            'storage' => 1024 * 1024 * 100, // 100MB in bytes
            'api_calls' => 1000,
            'projects' => 1,
            ...self::$registeredDefaults,
        ];
    }

    /**
     * Get a default limit for a specific key.
     */
    private function getDefaultLimit(string $key, mixed $default = null): mixed
    {
        return data_get($this->getDefaultLimits(), $key, $default);
    }

    /**
     * Check if an organization has access to a feature.
     */
    public function hasFeature(string $feature): bool
    {
        if (! $this->organization) {
            return false;
        }

        $subscription = $this->organization->activeSubscription();

        if (! $subscription) {
            return false;
        }

        return (bool) $subscription->plan?->hasFeature($feature);
    }

    /**
     * Get the remaining quota for a resource.
     */
    public function getRemaining(string $key, int $current): int
    {
        $limit = $this->getLimit($key);

        if (! is_numeric($limit) || (int) $limit < 0) {
            return -1; // Unlimited
        }

        return max(0, (int) $limit - $current);
    }

    /**
     * Get usage percentage for a resource.
     */
    public function getUsagePercentage(string $key, int $current): float
    {
        $limit = $this->getLimit($key);

        if (! is_numeric($limit) || (int) $limit < 0) {
            return 0;
        }

        if ((int) $limit === 0) {
            return $current > 0 ? 100 : 0;
        }

        return min(100, ($current / (int) $limit) * 100);
    }
}
