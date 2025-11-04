<?php

namespace Features\Compliance\GdprDelete\Providers;

use Features\Compliance\GdprDelete\Commands\ProcessScheduledDeletions;
use Illuminate\Support\ServiceProvider;

class GdprDeleteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessScheduledDeletions::class,
            ]);
        }
    }
}
