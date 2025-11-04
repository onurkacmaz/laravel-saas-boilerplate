<?php

namespace Features\Subscription\GetPlan\Providers;

use Illuminate\Support\ServiceProvider;

class GetPlanServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
