<?php

namespace Features\Subscription\Shared\Support;

use App\Models\Organization;
use Closure;
use Features\Subscription\Infrastructure\Eloquent\Models\Usage;
use Infrastructure\Tenant\TenantContext;
use RuntimeException;

class UsageTracker
{
    /**
     * Counters of plan-limited resources, by resource type.
     *
     * @var array<string, Closure(Organization): int>
     */
    private static array $counters = [];

    public function __construct(
        private ?Organization $organization = null
    ) {
        $this->organization = $organization ?? TenantContext::getOrganization();
    }

    /**
     * Teach the tracker how to count a resource limited by plans, so that
     * `limit:<resource>` and the usage summary see its real count. Register
     * counters from a service provider's boot method. A counter registered for
     * "users" or "api_calls" replaces the built-in one.
     *
     * @param  Closure(Organization): int  $counter
     */
    public static function registerCounter(string $resourceType, Closure $counter): void
    {
        self::$counters[$resourceType] = $counter;
    }

    public static function hasCounter(string $resourceType): bool
    {
        return isset(self::$counters[$resourceType]);
    }

    public static function forgetCounter(string $resourceType): void
    {
        unset(self::$counters[$resourceType]);
    }

    /**
     * Track usage for a specific resource.
     */
    public function track(
        string $resourceType,
        int $amount = 1,
        ?array $metadata = null
    ): Usage {
        if (! $this->organization) {
            throw new RuntimeException('No organization context set for usage tracking');
        }

        return Usage::query()->create([
            'organization_id' => $this->organization->id,
            'resource_type' => $resourceType,
            'amount' => $amount,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get total usage for a resource type.
     */
    public function getTotal(string $resourceType): int
    {
        if (! $this->organization) {
            return 0;
        }

        return (int) Usage::query()
            ->where('organization_id', $this->organization->id)
            ->where('resource_type', $resourceType)
            ->sum('amount');
    }

    /**
     * Get usage for the current period (current month).
     */
    public function getCurrentPeriodTotal(string $resourceType): int
    {
        if (! $this->organization) {
            return 0;
        }

        // A range on created_at (instead of whereMonth/whereYear) can use the index.
        return (int) Usage::query()
            ->where('organization_id', $this->organization->id)
            ->where('resource_type', $resourceType)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
    }

    /**
     * Get the current count for a resource (e.g., current number of users).
     */
    public function getCurrentCount(string $resourceType): int
    {
        if (! $this->organization) {
            return 0;
        }

        if (isset(self::$counters[$resourceType])) {
            return (int) (self::$counters[$resourceType])($this->organization);
        }

        if ($resourceType === 'users') {
            return $this->organization->users()->count();
        }

        // Metered resources are counted from the usage records of the current period.
        if ($resourceType === 'api_calls') {
            return $this->getCurrentPeriodTotal($resourceType);
        }

        // Resources without a counter (see registerCounter()) count as 0.
        return 0;
    }

    /**
     * Check if usage is within limits.
     */
    public function isWithinLimit(string $resourceType, ?int $current = null): bool
    {
        $current = $current ?? $this->getCurrentCount($resourceType);
        $limits = app(ResourceLimits::class, ['organization' => $this->organization]);

        return $limits->canPerform($resourceType, $current);
    }

    /**
     * Get a usage summary for all resources.
     */
    public function getSummary(): array
    {
        if (! $this->organization) {
            return [];
        }

        $limits = app(ResourceLimits::class, ['organization' => $this->organization]);
        $allLimits = $limits->getAllLimits();

        $summary = [];

        foreach ($allLimits as $resourceType => $limit) {
            $current = $this->getCurrentCount($resourceType);

            $summary[$resourceType] = [
                'current' => $current,
                'limit' => $limit,
                'remaining' => $limits->getRemaining($resourceType, $current),
                'percentage' => $limits->getUsagePercentage($resourceType, $current),
                'can_add' => $limits->canPerform($resourceType, $current),
            ];
        }

        return $summary;
    }
}
