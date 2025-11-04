<?php

namespace Features\Subscription\Shared\Listeners;

use Features\Subscription\Shared\Events\PaymentFailed;
use Features\Subscription\Shared\Notifications\PaymentFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentFailedNotification implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        $organization = $event->organization;

        // Send notification to an organization owner and admins
        if ($owner = $organization->owner) {
            $owner->notify(new PaymentFailedNotification($organization, $event->paymentData));
        }

        // Also notify all admins
        $organization->users()
            ->wherePivot('role', 'admin')
            ->get()
            ->each(fn ($member) => $member->notify(
                new PaymentFailedNotification($organization, $event->paymentData)
            ));
    }
}
