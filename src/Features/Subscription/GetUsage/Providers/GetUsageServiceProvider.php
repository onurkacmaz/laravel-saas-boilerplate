<?php

namespace Features\Subscription\GetUsage\Providers;

use Illuminate\Support\ServiceProvider;

class GetUsageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
