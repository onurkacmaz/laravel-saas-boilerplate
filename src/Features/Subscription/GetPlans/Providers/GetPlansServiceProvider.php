<?php

namespace Features\Subscription\GetPlans\Providers;

use Illuminate\Support\ServiceProvider;

class GetPlansServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
