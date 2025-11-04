<?php

namespace Features\Subscription\Shared\Events;

use Features\Subscription\Infrastructure\Eloquent\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription|\Laravel\Cashier\Subscription $subscription
    ) {}
}
