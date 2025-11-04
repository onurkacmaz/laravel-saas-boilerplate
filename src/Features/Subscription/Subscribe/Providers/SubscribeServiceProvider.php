<?php

namespace Features\Subscription\Subscribe\Providers;

use Features\Subscription\Shared\Events\SubscriptionCreated;
use Features\Subscription\Shared\Listeners\SendSubscriptionCreatedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SubscribeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(SubscriptionCreated::class, SendSubscriptionCreatedNotification::class);
    }
}
