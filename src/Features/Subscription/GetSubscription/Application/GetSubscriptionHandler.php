<?php

namespace Features\Subscription\GetSubscription\Application;

use Features\Subscription\Shared\Contracts\BillingSubscription;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetSubscriptionHandler
{
    public function __invoke(GetSubscription $command): BillingSubscription
    {
        $organization = TenantContext::getOrganization();

        $subscription = $organization->activeSubscription();

        if (! $subscription) {
            throw new NotFoundHttpException(__('No active subscription'));
        }

        return $subscription->load('plan');
    }
}
