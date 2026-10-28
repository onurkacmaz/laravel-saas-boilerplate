<?php

namespace Features\Subscription\Cancel\Application;

use Features\Subscription\Shared\Contracts\BillingSubscription;
use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CancelHandler
{
    public function __invoke(Cancel $command): ?BillingSubscription
    {
        $subscription = $command->organization->activeSubscription();

        if (! $subscription) {
            throw new NotFoundHttpException(__('No active subscription'));
        }

        // Already scheduled for cancellation: only an immediate cancel changes anything.
        if ($subscription->onGracePeriod() && ! $command->immediately) {
            return $subscription->fresh(['plan']);
        }

        if ($command->immediately) {
            $subscription->cancelNow();
        } else {
            $subscription->cancel();
        }

        event(new SubscriptionCanceled($subscription));

        return $subscription->fresh(['plan']);
    }
}
