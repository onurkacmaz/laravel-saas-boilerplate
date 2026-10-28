<?php

namespace Features\Subscription\OpenBillingPortal\Application;

use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Support\BillingUrls;

class OpenBillingPortalHandler
{
    public function __construct(
        private readonly BillingGateway $gateway,
    ) {}

    /**
     * A session of the payment provider's customer portal for the
     * organization, which returns to the application's billing page.
     */
    public function __invoke(OpenBillingPortal $command): string
    {
        return $this->gateway->portalUrl($command->organization, BillingUrls::portalReturn());
    }
}
