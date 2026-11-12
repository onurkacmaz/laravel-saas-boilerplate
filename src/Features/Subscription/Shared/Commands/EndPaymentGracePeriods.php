<?php

namespace Features\Subscription\Shared\Commands;

use App\Models\Organization;
use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Events\OrganizationSubscriptionChanged;
use Illuminate\Console\Command;

/**
 * No webhook arrives when a payment grace period runs out: the subscription
 * stays past due at the provider. This reports it once, with
 * OrganizationSubscriptionChanged, so modules bring their resources in line
 * with the plan now in effect.
 */
class EndPaymentGracePeriods extends Command
{
    protected $signature = 'subscriptions:end-payment-grace';

    protected $description = 'Report the past due subscriptions whose payment grace period is over';

    public function handle(BillingGateway $gateway): int
    {
        $count = 0;

        foreach ($gateway->paymentGracePeriodsEnded() as $subscription) {
            $subscription->markPaymentGraceEnded();

            $organization = Organization::query()->find($subscription->getAttribute('organization_id'));

            if ($organization) {
                event(new OrganizationSubscriptionChanged($organization, $organization->activeSubscription()?->plan));
                $count++;
            }
        }

        $this->info("Ended $count payment grace period(s).");

        return self::SUCCESS;
    }
}
