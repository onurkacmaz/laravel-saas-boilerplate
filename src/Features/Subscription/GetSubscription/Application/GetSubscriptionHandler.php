<?php

namespace Features\Subscription\GetSubscription\Application;

use App\Models\Organization;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Contracts\BillingSubscription;
use Infrastructure\Tenant\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetSubscriptionHandler
{
    public function __construct(
        private readonly BillingGateway $gateway,
    ) {}

    public function __invoke(GetSubscription $command): BillingSubscription
    {
        $organization = TenantContext::getOrganization();

        $subscription = $organization->activeSubscription() ?? $this->pastDueSubscription($organization);

        if (! $subscription) {
            throw new NotFoundHttpException(__('No active subscription'));
        }

        return $subscription->load('plan');
    }

    /**
     * A paid subscription past due beyond its payment grace period: the plan
     * is no longer in effect, but the organization can still pay for it, so
     * it is shown rather than no subscription at all.
     */
    private function pastDueSubscription(Organization $organization): ?BillingSubscription
    {
        return $this->gateway->subscriptions($organization)
            ->first(fn (BillingSubscription $subscription) => $subscription->pastDueSince() !== null);
    }
}
