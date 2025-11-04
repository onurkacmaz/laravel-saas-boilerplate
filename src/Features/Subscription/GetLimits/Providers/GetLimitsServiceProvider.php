<?php

namespace Features\Subscription\GetLimits\Providers;

use Illuminate\Support\ServiceProvider;

class GetLimitsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
