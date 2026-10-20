<?php

namespace Features\Subscription\Shared\Listeners;

use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Notifications\SubscriptionCreatedNotification;

class SendSubscriptionCreatedNotification
{
    /**
     * Handle the event.
     */
    public function handle(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription;
        $organization = $subscription->organization;

        if (! $organization) {
            return;
        }

        if ($owner = $organization->owner) {
            $owner->notify(new SubscriptionCreatedNotification($subscription));
        }
    }
}
