<?php

namespace Features\Subscription\Shared\Commands;

use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Features\Subscription\Shared\Notifications\TrialEndingSoonNotification;
use Illuminate\Console\Command;

class SendTrialEndingNotifications extends Command
{
    protected $signature = 'subscriptions:trial-ending';

    protected $description = 'Send notifications for trials ending soon';

    public function handle(): int
    {
        $threeDaysFromNow = now()->addDays(3)->startOfDay();
        $threeDaysFromNowEnd = now()->addDays(3)->endOfDay();

        $subscriptions = Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$threeDaysFromNow, $threeDaysFromNowEnd])
            ->with(['organization.owner', 'plan'])
            ->get();

        $count = 0;

        foreach ($subscriptions as $subscription) {
            if ($owner = $subscription->organization->owner) {
                $owner->notify(new TrialEndingSoonNotification($subscription));
                $count++;
            }
        }

        $this->info("Sent $count trial ending notifications.");

        return self::SUCCESS;
    }
}
