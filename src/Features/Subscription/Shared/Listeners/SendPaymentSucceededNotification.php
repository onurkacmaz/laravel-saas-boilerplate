<?php

namespace Features\Subscription\Shared\Listeners;

use Features\Subscription\Shared\Events\PaymentSucceeded;
use Features\Subscription\Shared\Notifications\PaymentSucceededNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentSucceededNotification implements ShouldQueue
{
    public function handle(PaymentSucceeded $event): void
    {
        $organization = $event->organization;

        // Send notification to an organization owner
        if ($owner = $organization->owner) {
            $owner->notify(new PaymentSucceededNotification($organization, $event->paymentData));
        }
    }
}
