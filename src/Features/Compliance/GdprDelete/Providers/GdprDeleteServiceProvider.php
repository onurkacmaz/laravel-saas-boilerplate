<?php

namespace Features\Compliance\GdprDelete\Providers;

use Features\Compliance\GdprDelete\Commands\ProcessScheduledDeletions;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Support\LoadsApiRoutes;

class GdprDeleteServiceProvider extends ServiceProvider
{
    use LoadsApiRoutes;

    public function boot(): void
    {
        $this->loadApiRoutesFrom(__DIR__.'/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessScheduledDeletions::class,
            ]);
        }
    }
}
