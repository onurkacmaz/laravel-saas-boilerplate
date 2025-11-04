<?php

namespace Features\Subscription\Shared\Support;

use App\Models\Organization;
use Features\Subscription\Infrastructure\Eloquent\Models\Usage;
use Infrastructure\Tenant\TenantContext;
use RuntimeException;

class UsageTracker
{
    public function __construct(
        private ?Organization $organization = null
    ) {
        $this->organization = $organization ?? TenantContext::getOrganization();
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

        return Usage::query()
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

        return Usage::query()
            ->where('organization_id', $this->organization->id)
            ->where('resource_type', $resourceType)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
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

        // This should be implemented based on actual resource counting,
        // For example, for users:
        if ($resourceType === 'users') {
            return $this->organization->users()->count();
        }

        // For other resources, you might need to implement specific logic
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
