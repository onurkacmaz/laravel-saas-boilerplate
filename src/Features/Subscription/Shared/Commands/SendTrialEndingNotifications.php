<?php

namespace Features\Subscription\Shared\Commands;

use Features\Subscription\Shared\Contracts\BillingGateway;
use Features\Subscription\Shared\Notifications\TrialEndingSoonNotification;
use Illuminate\Console\Command;

class SendTrialEndingNotifications extends Command
{
    protected $signature = 'subscriptions:trial-ending';

    protected $description = 'Send notifications for trials ending soon';

    public function handle(BillingGateway $gateway): int
    {
        $threeDaysFromNow = now()->addDays(3)->startOfDay();
        $threeDaysFromNowEnd = now()->addDays(3)->endOfDay();

        $subscriptions = $gateway->trialsEndingBetween($threeDaysFromNow, $threeDaysFromNowEnd);

        $count = 0;

        foreach ($subscriptions as $subscription) {
            if ($owner = $subscription->organization?->owner) {
                $owner->notify(new TrialEndingSoonNotification($subscription));
                $count++;
            }
        }

        $this->info("Sent $count trial ending notifications.");

        return self::SUCCESS;
    }
}
