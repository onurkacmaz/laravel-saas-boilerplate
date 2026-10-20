<?php

namespace Features\Subscription\Shared\Listeners;

use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Notifications\SubscriptionCanceledNotification;

class SendSubscriptionCanceledNotification
{
    /**
     * Handle the event.
     */
    public function handle(SubscriptionCanceled $event): void
    {
        $subscription = $event->subscription;
        $organization = $subscription->organization;

        if (! $organization) {
            return;
        }

        if ($owner = $organization->owner) {
            $owner->notify(new SubscriptionCanceledNotification($subscription));
        }
    }
}
