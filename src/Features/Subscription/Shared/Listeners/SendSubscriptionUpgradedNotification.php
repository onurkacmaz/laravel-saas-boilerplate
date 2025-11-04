<?php

namespace Features\Subscription\Shared\Listeners;

use Features\Subscription\Shared\Events\SubscriptionUpgraded;
use Features\Subscription\Shared\Notifications\SubscriptionUpgradedNotification;

class SendSubscriptionUpgradedNotification
{
    /**
     * Handle the event.
     */
    public function handle(SubscriptionUpgraded $event): void
    {
        $subscription = $event->subscription;
        $organization = $subscription->organization;
        $oldPlan = $event->oldPlan;

        if ($owner = $organization->owner) {
            $owner->notify(new SubscriptionUpgradedNotification($subscription, $oldPlan));
        }
    }
}
