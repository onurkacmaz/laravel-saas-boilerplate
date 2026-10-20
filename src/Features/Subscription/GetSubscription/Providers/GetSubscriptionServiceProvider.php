<?php

namespace Features\Subscription\GetSubscription\Providers;

use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class GetSubscriptionServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        // Load routes
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
