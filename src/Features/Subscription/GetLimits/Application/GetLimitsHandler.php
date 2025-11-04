<?php

namespace Features\Subscription\GetLimits\Application;

use Features\Subscription\Shared\Support\ResourceLimits;
use Infrastructure\Tenant\TenantContext;

class GetLimitsHandler
{
    public function __invoke(GetLimits $command): array
    {
        $organization = TenantContext::getOrganization();

        return new ResourceLimits($organization)->getAllLimits();
    }
}
