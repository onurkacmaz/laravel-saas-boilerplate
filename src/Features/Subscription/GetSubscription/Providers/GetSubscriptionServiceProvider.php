<?php

namespace Features\Subscription\GetSubscription\Providers;

use Illuminate\Support\ServiceProvider;

class GetSubscriptionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
