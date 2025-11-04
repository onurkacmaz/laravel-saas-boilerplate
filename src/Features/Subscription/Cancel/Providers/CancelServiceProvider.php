<?php

namespace Features\Subscription\Cancel\Providers;

use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Listeners\SendSubscriptionCanceledNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CancelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(SubscriptionCanceled::class, SendSubscriptionCanceledNotification::class);
    }
}
