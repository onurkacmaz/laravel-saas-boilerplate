<?php

namespace Features\Subscription\Cancel\Providers;

use Features\Subscription\Shared\Events\SubscriptionCanceled;
use Features\Subscription\Shared\Listeners\SendSubscriptionCanceledNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class CancelServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(SubscriptionCanceled::class, SendSubscriptionCanceledNotification::class);
    }
}
