<?php

namespace Features\Subscription\OpenBillingPortal\Application;

use App\Models\Organization;

class OpenBillingPortal
{
    public function __construct(
        public Organization $organization,
    ) {}
}
