<?php

namespace Features\Subscription\Shared\Support;

use App\Models\Organization;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Throwable;

/**
 * Stops billing an organization that goes away: every valid subscription,
 * free or paid, is canceled at once.
 */
class CancelSubscriptions
{
    public function __construct(
        private readonly BillingGateway $gateway,
    ) {}

    public function __invoke(Organization $organization): void
    {
        $subscriptions = $organization->subscriptions()->local()->get()
            ->concat($this->gateway->subscriptions($organization));

        foreach ($subscriptions as $subscription) {
            if (! $subscription->valid()) {
                continue;
            }

            try {
                $subscription->cancelNow();
            } catch (Throwable $exception) {
                report($exception);
                $subscription->markAsCanceled();
            }
        }
    }
}
