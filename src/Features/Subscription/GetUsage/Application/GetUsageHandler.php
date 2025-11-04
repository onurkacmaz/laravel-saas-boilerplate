<?php

namespace Features\Subscription\GetUsage\Application;

use Features\Subscription\Shared\Support\UsageTracker;
use Infrastructure\Tenant\TenantContext;

class GetUsageHandler
{
    public function __invoke(GetUsage $command): array
    {
        $organization = TenantContext::getOrganization();

        $tracker = new UsageTracker($organization);

        return $tracker->getSummary();
    }
}
