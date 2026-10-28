<?php

namespace Features\Subscription\PaddleWebhook\Providers;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class PaddleWebhookServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'billing');

        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        // The checkout page is a page, not an API endpoint: the API's
        // Content-Security-Policy would block Paddle.js.
        if (! ($this->app instanceof CachesRoutes && $this->app->routesAreCached())) {
            Route::middleware('web')->group(__DIR__.'/../Routes/web.php');
        }
    }
}
