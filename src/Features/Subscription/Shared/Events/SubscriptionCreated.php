<?php

namespace Features\Subscription\Shared\Events;

use Features\Subscription\Shared\Contracts\BillingSubscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public BillingSubscription $subscription
    ) {}
}
