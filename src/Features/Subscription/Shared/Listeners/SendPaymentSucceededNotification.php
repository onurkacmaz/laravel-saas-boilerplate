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

        // The $0 invoice or transaction that starts a free trial charged
        // nothing: there is no payment to confirm.
        $amount = (int) ($event->paymentData['amount_paid'] ?? $event->paymentData['amount_received'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        // Send notification to an organization owner
        if ($owner = $organization->owner) {
            $owner->notify(new PaymentSucceededNotification($organization, $event->paymentData));
        }
    }
}
